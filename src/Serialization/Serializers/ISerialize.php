<?php

namespace Rabbit\Dapr\Serialization\Serializers;

use Rabbit\Dapr\Serialization\ISerializer;

/**
 * Interface ISerialize
 * @package Rabbit\Dapr\Serialization\Serializers
 */
interface ISerialize
{
    public function serialize(mixed $value, ISerializer $serializer): mixed;
}
