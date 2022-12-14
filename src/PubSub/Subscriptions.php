<?php

namespace Rabbit\Dapr\PubSub;

use Rabbit\Dapr\Serialization\ISerializer;
use Rabbit\Dapr\Serialization\Serializers\ISerialize;

/**
 * Class Subscriptions
 * @package Rabbit\Dapr\PubSub
 */
class Subscriptions implements ISerialize
{
    /**
     * Subscriptions constructor.
     *
     * @param Subscription[] $subscriptions
     */
    public function __construct(public array $subscriptions = [])
    {
    }

    /**
     * @param mixed $value
     * @param ISerializer $serializer
     *
     * @return mixed
     * @codeCoverageIgnore via integration tests
     */
    public function serialize(mixed $value, ISerializer $serializer): mixed
    {
        return $serializer->as_array($this->subscriptions);
    }
}
