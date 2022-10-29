<?php

namespace Rabbit\Dapr\Actors\Generators;

use Rabbit\Dapr\Actors\IActor;
use Rabbit\Dapr\Client\DaprClient;

/**
 * Interface IGenerateProxy
 *
 * All generators implement this interface.
 *
 * @package Rabbit\Dapr\Actors\Generators
 */
interface IGenerateProxy
{
    public function __construct(
        string $interface,
        string $dapr_type,
        DaprClient $client
    );

    /**
     * Get a proxied type
     *
     * @param string $id The id of the type
     *
     * @return IActor An actor
     */
    public function get_proxy(string $id);
}
