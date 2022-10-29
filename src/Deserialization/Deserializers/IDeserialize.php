<?php

namespace Rabbit\Dapr\Deserialization\Deserializers;

use Rabbit\Dapr\Deserialization\IDeserializer;

/**
 * Interface IDeserialize
 *
 * All deserializers should implement this interface
 *
 * @package Rabbit\Dapr\Deserialization\Deserializers
 */
interface IDeserialize
{
    public static function deserialize(mixed $value, IDeserializer $deserializer): mixed;
}
