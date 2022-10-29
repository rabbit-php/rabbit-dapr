<?php

namespace Rabbit\Dapr;

use Rabbit\Dapr\Actors\ActorConfig;
use Rabbit\Dapr\Actors\ActorReference;
use Rabbit\Dapr\Actors\ActorRuntime;
use Rabbit\Dapr\Actors\HealthCheck;
use Rabbit\Dapr\Actors\IActor;
use Rabbit\Dapr\Actors\Reminder;
use Rabbit\Dapr\PubSub\Subscriptions;
use Rabbit\Dapr\Serialization\ISerializer;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Rabbit\Dapr\Middleware\IRequestMiddleware;
use Rabbit\Dapr\Middleware\IResponseMiddleware;
use Rabbit\Web\ResponseContext;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Class Dapr
 * @package Dapr
 */
class Dapr implements MiddlewareInterface
{
    /**
     * Dapr constructor.
     *
     * @param ISerializer $serializer
     * @param LoggerInterface $logger
     * @param RouteCollector $routeCollector
     */
    public function __construct(
        protected ISerializer $serializer,
        protected LoggerInterface $logger,
        protected RouteCollector $routeCollector
    ) {
        $this->add_dapr_routes();
    }

    /**
     * @param string $route
     * @param callable $callback
     */
    public function post(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute('POST', $route, $callback);
    }

    /**
     * @param string $route
     * @param callable $callback
     */
    public function options(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute('OPTIONS', $route, $callback);
    }

    /**
     * @param string $route
     * @param callable $callback
     */
    public function patch(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute('PATCH', $route, $callback);
    }

    /**
     * @param string $route
     * @param callable $callback
     */
    public function any(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'], $route, $callback);
    }

    public function add_dapr_routes(): void
    {
        /**
         * Actors
         */
        // deactivate an actor
        $this->delete(
            '/actors/{actor_type}/{actor_id}',
            function (
                string $actor_type,
                string $actor_id,
                ActorRuntime $runtime
            ) {
                $runtime->resolve_actor(
                    new ActorReference($actor_id, $actor_type),
                    fn (IActor $actor) => $runtime->deactivate_actor($actor, $actor_type)
                );
            }
        );
        // allow calling an actor
        $this->put(
            '/actors/{actor_type}/{actor_id}/method/{method_name}[/{reminder_name}]',
            function (
                RequestInterface $request,
                ResponseInterface $response,
                string $actor_type,
                string $actor_id,
                string $method_name,
                ?string $reminder_name,
                ActorRuntime $runtime,
            ) {
                $arg = json_decode($request->getBody()->getContents(), true);
                if ($method_name === 'remind') {
                    $runtime->resolve_actor(
                        new ActorReference($actor_id, $actor_type),
                        fn (IActor $actor) => $actor->remind($reminder_name, Reminder::from_api($reminder_name, $arg))
                    );
                } elseif ($method_name === 'timer') {
                    $body = $response->getBody();
                    $body->seek(0);
                    $runtime->resolve_actor(
                        new ActorReference($actor_id, $actor_type),
                        fn (IActor $actor) => $body->write($this->serializer->as_json($runtime->do_method($actor, $arg['callback'], $arg['data'])))
                    );
                } else {
                    $body = $response->getBody();
                    $body->seek(0);
                    $runtime->resolve_actor(
                        new ActorReference($actor_id, $actor_type),
                        fn (IActor $actor) => $body->write($this->serializer->as_json($runtime->do_method($actor, $method_name, $arg)))
                    );
                }

                return $response;
            }
        );
        // handle configuration
        $this->get('/dapr/config', fn (ActorConfig $config) => $config);
        $this->get(
            '/healthz',
            fn (HealthCheck $check, ResponseInterface $response) => $response->withStatus(
                $check->do_health_check() ? 200 : 500
            )
        );
        /**
         * Publish/Subscribe
         */
        $this->get('/dapr/subscribe', fn (Subscriptions $subscriptions) => $subscriptions);
    }

    /**
     * @param string $route
     * @param callable $callback
     */
    public function delete(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute('DELETE', $route, $callback);
    }

    /**
     * @param string $route
     * @param callable $callback
     */
    public function put(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute('PUT', $route, $callback);
    }

    public function get(string $route, callable $callback): void
    {
        $this->routeCollector->addRoute('GET', $route, $callback);
    }

    /**
     * Creates and handles a request
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    private function handleRequest(RequestInterface $request): null|ResponseInterface
    {
        $this->logger->debug(
            'Handling request: {method} {uri}',
            ['method' => $request->getMethod(), 'uri' => $request->getUri()]
        );

        $response = ResponseContext::get();

        $parameters = ['request' => $request, 'response' => $response];

        $dispatcher = new Dispatcher\GroupCountBased($this->routeCollector->getData());

        $route_info = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
        switch ($route_info[0]) {
            case Dispatcher::NOT_FOUND:
            default:
                return null;
            case Dispatcher::METHOD_NOT_ALLOWED:
                return null;
            case Dispatcher::FOUND:
                $parameters += $route_info[2];
                $callback = $route_info[1];

                $actual_response = $response;
                $response = $this->run($callback, $parameters);

                if ($response instanceof ResponseInterface) {
                    return $response;
                }

                $body = $actual_response->getBody();
                $body->seek(0);
                if (is_array($response) && isset($response['code'])) {
                    if (isset($response['code'])) {
                        $actual_response = $actual_response->withStatus($response['code']);
                    }
                    if (isset($response['body'])) {
                        $actual_response = $body->write($this->serializer->as_json($response['body']));
                    }

                    return $actual_response;
                }

                if ($response instanceof DaprResponse) {
                    $body->write($this->serializer->as_json($response->data));
                    $actual_response = $actual_response->withStatus($response->code);

                    foreach ($response->headers as $header => $value) {
                        $actual_response = $actual_response->withHeader($header, $value);
                    }

                    return $actual_response;
                }
                $body->write($this->serializer->as_json($response));
                return $actual_response;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (null !== $response = $this->handleRequest($this->apply_request_middleware($request))) {
            return $this->apply_response_middleware($response);
        }
        return $handler->handle($request);
    }

    /**
     * @param callable $callback
     * @param array $parameters
     *
     * @return mixed
     * @throws InvalidArgumentException
     *
     */
    public function run(callable $callback, array $parameters = []): mixed
    {
        $callableReflection = new ReflectionFunction($callback);
        $args = $this->getParameters($callableReflection, $parameters, []);
        ksort($args);
        $diff = array_diff_key($callableReflection->getParameters(), $args);
        $parameter = reset($diff);
        if ($parameter && \assert($parameter instanceof ReflectionParameter) && !$parameter->isVariadic()) {
            throw new InvalidArgumentException(sprintf(
                'Unable to invoke the callable because no value was given for parameter %d ($%s)',
                $parameter->getPosition() + 1,
                $parameter->name
            ));
        }

        return call_user_func_array($callback, $args);
    }

    private function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ): array {
        $parameters = $reflection->getParameters();

        // Skip parameters already resolved
        if (!empty($resolvedParameters)) {
            $parameters = array_diff_key($parameters, $resolvedParameters);
        }

        foreach ($parameters as $index => $parameter) {
            if (array_key_exists($parameter->name, $providedParameters)) {
                $resolvedParameters[$index] = $providedParameters[$parameter->name];
                continue;
            }
            \assert($parameter instanceof \ReflectionParameter);
            if ($parameter->isDefaultValueAvailable()) {
                try {
                    $resolvedParameters[$index] = $parameter->getDefaultValue();
                } catch (ReflectionException $e) {
                    // Can't get default values from PHP internal classes and functions
                }
            } else {
                $parameterType = $parameter->getType();
                if ($parameterType && $parameterType->allowsNull()) {
                    $resolvedParameters[$index] = null;
                }
            }
            $parameterType = $parameter->getType();
            if (!$parameterType) {
                // No type
                continue;
            }
            if (!$parameterType instanceof ReflectionNamedType) {
                // Union types are not supported
                continue;
            }
            if ($parameterType->isBuiltin()) {
                // Primitive types are not supported
                continue;
            }

            $parameterClass = $parameterType->getName();
            if ($parameterClass === 'self') {
                $parameterClass = $parameter->getDeclaringClass()->getName();
            }

            $resolvedParameters[$index] = service($parameterClass);
        }

        return $resolvedParameters;
    }

    protected function apply_request_middleware(RequestInterface $request): RequestInterface
    {
        $middlewares = config('dapr.http.middleware.request');
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof IRequestMiddleware) {
                $request = $middleware->request($request);
            } else {
                throw new \LogicException('Request middleware must implement Rabbit\Dapr\Middleware\IRequestMiddleware');
            }
        }

        return $request;
    }

    protected function apply_response_middleware(ResponseInterface $response): ResponseInterface
    {
        $middlewares = config('dapr.http.middleware.response');
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof IResponseMiddleware) {
                $response = $middleware->response($response);
            } else {
                throw new \LogicException('Response middleware must implement Rabbit\Dapr\Middleware\IResponseMiddleware');
            }
        }

        return $response;
    }
}
