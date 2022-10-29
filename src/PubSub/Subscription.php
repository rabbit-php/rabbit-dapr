<?php

namespace Rabbit\Dapr\PubSub;

/**
 * Class Subscription
 * @package Rabbit\Dapr\PubSub
 * @codeCoverageIgnore via integration tests
 */
class Subscription
{
    public function __construct(public string $pubsubname, public string $topic, public string $route)
    {
    }
}
