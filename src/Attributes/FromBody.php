<?php

namespace Rabbit\Dapr\Attributes;

use Attribute;

/**
 * Class FromBody
 * @package Rabbit\Dapr\Attributes
 *
 * Indicates that the parameter should be deserialized from the body
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromBody
{
}
