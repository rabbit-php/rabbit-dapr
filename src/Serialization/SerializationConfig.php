<?php

namespace Rabbit\Dapr\Serialization;

use Rabbit\Dapr\Serialization\Serializers\DateInterval;
use Rabbit\Dapr\Serialization\Serializers\DateTime;
use Rabbit\Dapr\Serialization\Serializers\ISerialize;
use Rabbit\Dapr\State\StateItem;

/**
 * Class SerializationConfig
 * @package Rabbit\Dapr\Serialization
 */
class SerializationConfig
{
    /**
     * SerializationConfig constructor.
     *
     * @param ISerialize[] $serializers
     */
    public function __construct(protected array $serializers = [])
    {
        if (empty($this->serializers[\DateInterval::class])) {
            $this->add(\DateInterval::class, new DateInterval());
        }
        if (empty($this->serializers[\DateTime::class])) {
            $this->add(\DateTime::class, new DateTime());
        }
        if (empty($this->serializers[StateItem::class])) {
            $this->add(StateItem::class, new Serializers\StateItem());
        }
    }

    public function add(string $type, ISerialize $serializer): void
    {
        $this->serializers[$type] = $serializer;
    }
}
