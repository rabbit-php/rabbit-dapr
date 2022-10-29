<?php

namespace Rabbit\Dapr\State\Attributes;

use Attribute;

/**
 * Class StateStore
 *
 * Provides the name of a state store to store an object in
 *
 * @package Rabbit\Dapr\State\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class StateStore
{
    public function __construct(public string $name, public string $consistency)
    {
    }
}
