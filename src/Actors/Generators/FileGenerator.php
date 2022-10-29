<?php

namespace Rabbit\Dapr\Actors\Generators;

use Rabbit\Dapr\Actors\ActorReference;
use Rabbit\Dapr\Actors\ActorTrait;
use Rabbit\Dapr\Actors\Attributes\DaprType;
use Rabbit\Dapr\Actors\Attributes\Delete;
use Rabbit\Dapr\Actors\Attributes\Get;
use Rabbit\Dapr\Actors\Attributes\Post;
use Rabbit\Dapr\Actors\Attributes\Put;
use Rabbit\Dapr\Actors\IActor;
use Rabbit\Dapr\Client\DaprClient;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Type;
use ReflectionClass;
use ReflectionException;

/**
 * Class FileGenerator
 *
 * Generates an executable PHP file and evals it to create a proxy.
 *
 * @package Rabbit\Dapr\Actors\Generators
 */
class FileGenerator extends GenerateProxy
{
    #[Pure] public function __construct(
        string $interface,
        string $dapr_type,
        DaprClient $client,
        private array $usings = []
    ) {
        parent::__construct($interface, $dapr_type, $client);
    }

    /**
     * Returns a string that can be saved as a file
     *
     * @param string $interface The interface to generate
     * @param string|null $override_type Allows overriding the dapr type
     *
     * @return PhpFile
     * @throws ReflectionException
     */
    public static function generate(
        string $interface,
        string|null $override_type = null
    ): PhpFile {
        $reflected_interface = new ReflectionClass($interface);
        $type = $override_type ?? ($reflected_interface->getAttributes(
            DaprType::class
        )[0] ?? null)?->newInstance()->type;

        // @codeCoverageIgnoreStart
        if (empty($type)) {
            throw new LogicException("$interface must have a DaprType attribute");
        }
        // @codeCoverageIgnoreEnd

        $generator = create(FileGenerator::class, ['interface' => $interface, 'dapr_type' => $type], singleTon: false);

        return $generator->generate_file();
    }

    /**
     * @inheritDoc
     */
    public function get_proxy(string $id): IActor
    {
        if (!class_exists($this->get_full_class_name())) {
            foreach ($this->generate_file($id)->getNamespaces() as $namespace) {
                eval($namespace);
            }
        }
        $reflection = new ReflectionClass($this->get_full_class_name());

        /**
         * @var IActor $proxy
         */
        $proxy = $reflection->newInstance($this->client);
        $proxy->id = $id;

        return $proxy;
    }

    public function generate_file(string $id): PhpFile
    {
        // configure class
        $interface = ClassType::from($this->interface);
        $interface->addImplement($this->interface);
        $interface->addProperty('id')->setPublic()->setType(Type::STRING);
        $interface->setClass();
        $interface->setName($this->get_short_class_name());
        $interface->addTrait(ActorTrait::class);
        $interface->addProperty('DAPR_TYPE', $this->dapr_type)->setType(Type::STRING)->setPublic();
        $interface->addProperty('reference')->setPrivate()->setType(ActorReference::class);
        $interface->setFinal(true);

        // maybe implement IActor
        $reflected_interface = new ReflectionClass($interface);
        if (!$reflected_interface->isSubclassOf(IActor::class)) {
            $interface->addImplement(IActor::class);
        }

        $methods = $this->get_methods($interface);
        foreach ($methods as $method) {
            if ($interface->hasMethod($method->getName())) {
                $interface->removeMethod($method);
            }
            $method = $this->generate_method($method, $id);
            if ($method) {
                $interface->addMember($method);
            }
        }
        $interface->addMember($this->generate_constructor());
        $interface->addMember($this->generate_reference());

        // configure file
        $file = new PhpFile();
        $file->addComment('This file was automatically generated.');
        $namespace = $file->addNamespace($this->get_namespace());

        // configure namespace
        $namespace->add($interface);
        $namespace->addUse(IActor::class);
        $namespace->addUse(DaprType::class);
        $namespace->addUse(ActorTrait::class);
        $namespace->addUse(ActorReference::class);
        foreach ($this->usings as $using) {
            if (class_exists($using)) {
                $namespace->addUse($using);
            }
        }

        return $file;
    }

    protected function generate_constructor(): Method
    {
        $method = new Method('__construct');
        $method->addPromotedParameter('client')->setType(DaprClient::class)->setPrivate();
        $method->setPublic();
        $this->usings[] = DaprClient::class;
        $method->setBody('');

        return $method;
    }

    protected function generate_reference(): Method
    {
        $method = new Method('_get_actor_reference');
        $method->setPrivate();
        $method->setReturnType(ActorReference::class);
        $method->setBody('return $this->reference ??= new ActorReference($this->id, $this->DAPR_TYPE);');
        return $method;
    }

    /**
     * @inheritDoc
     */
    protected function generate_proxy_method(Method $method, string $id): Method
    {
        $params = array_values($method->getParameters());
        $method->setPublic();
        $http_method = 'GET';
        if (!empty($params)) {
            if ($params[0]->isReference()) {
                throw new LogicException(
                    "Cannot pass references between actors/methods.\nMethod: {$method->getName()}"
                );
            }
            // @codeCoverageIgnoreEnd
            $this->usings = array_merge($this->usings, self::get_types($params[0]->getType()));
            $method->addBody('$data = $?;', [array_values($method->getParameters())[0]->getName()]);
            $http_method = 'POST';
        }

        foreach ($method->getAttributes() as $attribute) {
            $http_method = match ($attribute->getName()) {
                Get::class => 'GET',
                Delete::class => 'DELETE',
                Post::class => 'POST',
                Put::class => 'PUT',
                default => $http_method
            };
        }

        $method->addBody('$current_method = ?;', [$method->getName()]);
        $method->addBody('$http_method = ?;', [$http_method]);
        $method->addBody(
            '$result = $this->client->invokeActorMethod($http_method, $this->_get_actor_reference(), $current_method, $data \?\? null, ?);',
            [$method->getReturnType() ?? 'array']
        );
        $return_type = $method->getReturnType() ?? Type::MIXED;
        if ($return_type !== Type::VOID) {
            $this->usings = array_merge($this->usings, self::get_types($return_type));
            $method->addBody('return $result;');
        }

        return $method;
    }

    /**
     * Converts a type into a list of well-known types.
     *
     * @param string|null $type The type string
     *
     * @return array An array of types
     */
    #[Pure] private static function get_types(string|null $type): array
    {
        if ($type === null) {
            return [Type::VOID];
        }

        return explode('|', $type);
    }

    /**
     * @inheritDoc
     */
    protected function generate_failure_method(Method $method): Method
    {
        $method->addBody('throw new \LogicException("Cannot call ? outside the actor");', [$method->getName()]);
        $method->setPublic();

        return $method;
    }

    /**
     * @inheritDoc
     */
    protected function generate_get_id(Method $method, string $id): Method
    {
        $get_id = new Method('get_id');
        $get_id->setReturnType(Type::STRING);
        $get_id->setPublic();
        $get_id->addBody('return $this->id;');

        return $get_id;
    }
}
