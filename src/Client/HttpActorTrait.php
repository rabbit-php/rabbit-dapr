<?php

namespace Rabbit\Dapr\Client;

use Rabbit\Dapr\Actors\IActorReference;
use Rabbit\Dapr\Actors\Internal\Caches\KeyNotFound;
use Rabbit\Dapr\Actors\Internal\KeyResponse;
use Rabbit\Dapr\Actors\Reminder;
use Rabbit\Dapr\Actors\Timer;
use Rabbit\Dapr\State\Internal\Transaction;
use GuzzleHttp\Promise\FulfilledPromise;

/**
 * Trait HttpActorTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpActorTrait
{
    public function invokeActorMethod(
        string $httpMethod,
        IActorReference $actor,
        string $method,
        mixed $parameter = null,
        string $as = 'array'
    ): mixed {
        $uri = "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/method/$method";
        $options = ['json' => $parameter];
        $response = match ($httpMethod) {
            'GET' => $this->httpClient->get($uri),
            'POST' => $this->httpClient->post($uri, $options),
            'PUT' => $this->httpClient->put($uri, $options),
            'DELETE' => $this->httpClient->delete($uri),
            default => throw new \InvalidArgumentException(
                "$httpMethod is not a supported actor invocation method. Must be GET/POST/PUT/DELETE"
            )
        };

        return $this->deserializer->from_json($as, $response->getBody()->getContents());
    }

    public function saveActorState(IActorReference $actor, Transaction $transaction): bool
    {
        if ($transaction->is_empty()) {
            return new FulfilledPromise(true);
        }

        $options = ['body' => $this->serializer->as_json($transaction->get_transaction())];
        return $this->httpClient->post(
            "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/state",
            $options
        )->getStatusCode() === 204;
    }

    public function getActorState(IActorReference $actor, string $key, string $as = 'array'): mixed
    {
        $response = $this->httpClient->get("/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/state/$key");
        return match ($response->getStatusCode()) {
            KeyResponse::SUCCESS => $this->deserializer->from_json($as, $response->getBody()->getContents()),
            KeyResponse::KEY_NOT_FOUND => throw new KeyNotFound(),
        };
    }

    public function createActorReminder(
        IActorReference $actor,
        Reminder $reminder
    ): bool {
        return $this->httpClient->post(
            "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/reminders/{$reminder->name}",
            [
                'body' => $this->serializer->as_json($reminder)
            ]
        )->getStatusCode() === 204;
    }

    public function getActorReminder(IActorReference $actor, string $name): ?Reminder
    {
        $response = $this->httpClient->get(
            "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/reminders/$name"
        );

        /** @var ?Reminder $reminder */
        $reminder = $response->getStatusCode() === 200 ? $this->deserializer->from_json(
            Reminder::class,
            $response->getBody()->getContents()
        ) : null;
        if ($reminder === null) {
            return null;
        }
        $reminder->name = $name;
        return $reminder;
    }

    public function deleteActorReminder(IActorReference $actor, string $name): bool
    {
        return $this->httpClient->delete(
            "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/reminders/$name"
        )->getStatusCode() === 204;
    }

    public function createActorTimer(
        IActorReference $actor,
        Timer $timer
    ): bool {
        return $this->httpClient->post(
            "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/timers/{$timer->name}",
            [
                'body' => $this->serializer->as_json($timer)
            ]
        )->getStatusCode() === 204;
    }

    public function deleteActorTimer(IActorReference $actor, string $name): bool
    {
        return $this->httpClient->delete(
            "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/timers/$name"
        )->getStatusCode() === 204;
    }
}
