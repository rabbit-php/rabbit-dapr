<?php

namespace Rabbit\Dapr\Serialization\Serializers;

use Rabbit\Dapr\Serialization\ISerializer;

/**
 * Class StateItem
 * @package Rabbit\Dapr\Serialization\Serializers
 */
class StateItem implements ISerialize
{
    public function serialize(mixed $value, ISerializer $serializer): mixed
    {
        if ($value instanceof \Rabbit\Dapr\State\StateItem) {
            $item = [
                'key'   => $value->key,
                'value' => $serializer->as_array($value->value),
            ];
            if (isset($value->etag)) {
                $item['etag']    = $value->etag;
                $item['options'] = [
                    'consistency' => $value->consistency->get_consistency(),
                    'concurrency' => $value->consistency->get_concurrency(),
                ];
            }
            if ( ! empty($value->metadata)) {
                $item['metadata'] = $value->metadata;
            }

            return $item;
        }

        return $value;
    }
}
