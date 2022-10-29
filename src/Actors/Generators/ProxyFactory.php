<?php

namespace Rabbit\Dapr\Actors\Generators;

use Rabbit\Dapr\Client\DaprClient;
use InvalidArgumentException;

/**
 * Class ProxyFactory
 *
 * Factory for creating an actor proxy.
 *
 * @package Rabbit\Dapr\Actors\Generators
 */
class ProxyFactory
{
    public const GENERATED = 0;
    public const GENERATED_CACHED = 1;
    public const DYNAMIC = 2;
    public const ONLY_EXISTING = 4;

    /**
     * ProxyFactory constructor.
     *
     * @param int $mode
     */
    public function __construct(private int $mode, private DaprClient $client)
    {
    }

    /**
     * @param $interface
     * @param $dapr_type
     *
     * @return IGenerateProxy
     */
    public function get_generator(string $interface, string $dapr_type): IGenerateProxy
    {
        return match ($this->mode) {
            ProxyFactory::DYNAMIC => new DynamicGenerator($interface, $dapr_type, $this->client),
            ProxyFactory::GENERATED_CACHED => new CachedGenerator($interface, $dapr_type, $this->client),
            ProxyFactory::GENERATED => new FileGenerator($interface, $dapr_type, $this->client),
            ProxyFactory::ONLY_EXISTING => new ExistingOnly($interface, $dapr_type, $this->client),
            default => throw new InvalidArgumentException('mode must be a supported mode'),
        };
    }
}
