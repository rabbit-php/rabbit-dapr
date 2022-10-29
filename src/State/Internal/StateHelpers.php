<?php

namespace Rabbit\Dapr\State\Internal;

use Rabbit\Dapr\State\Attributes\StateStore;
use LogicException;
use ReflectionClass;

/**
 * Trait StateHelpers
 * @package Rabbit\Dapr\State\Internal
 */
trait StateHelpers
{
    /**
     * Get the StateStore attribute for the current class.
     *
     * @param ReflectionClass $reflection
     *
     * @return StateStore
     */
    protected static function get_description(ReflectionClass $reflection): StateStore
    {
        foreach ($reflection->getAttributes(StateStore::class) as $attribute) {
            return $attribute->newInstance();
        }
        throw new LogicException('Tried to load state without a Rabbit\Dapr\State\Attributes\StateStore attribute');
    }
}
