<?php

namespace Rabbit\Dapr\Deserialization\Deserializers;

use Rabbit\Dapr\Deserialization\IDeserializer;
use DateInterval as PhpDateInterval;
use Exception;

/**
 * Class DateInterval
 * @package Rabbit\Dapr\Deserialization\Deserializers
 */
class DateInterval implements IDeserialize
{
    /**
     * @param mixed $value
     * @param IDeserializer $deserializer
     *
     * @return PhpDateInterval
     * @throws Exception
     */
    public static function deserialize(mixed $value, IDeserializer $deserializer): PhpDateInterval
    {
        return new PhpDateInterval($value);
    }
}
