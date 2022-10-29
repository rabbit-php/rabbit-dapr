<?php

namespace Rabbit\Dapr\Serialization\Attributes;

use Attribute;

/**
 * Class AlwaysObject
 *
 * Marks an array as always an object, even when empty
 *
 * @package Rabbit\Dapr\Serialization\Attributes
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class AlwaysObject
{
}
