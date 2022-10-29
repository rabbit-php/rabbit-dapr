<?php

namespace Rabbit\Dapr\Client;

use Rabbit\Dapr\exceptions\DaprException;

/**
 * Trait HttpPubSubTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpPubSubTrait
{
    /**
     * @throws DaprException
     */
    public function publishEvent(
        string $pubsubName,
        string $topicName,
        mixed $data,
        array $metadata = [],
        string $contentType = 'application/json'
    ): void {
        $options = [
            'query' => array_merge(
                ...array_map(fn ($key, $value) => ["metadata.$key" => $value], array_keys($metadata), $metadata)
            ),
            'json' => $data,
            'headers' => [
                'Content-Type' => $contentType,
            ]
        ];
        $pubsubName = rawurlencode($pubsubName);
        $topicName = rawurlencode($topicName);
        $this->httpClient->post("/v1.0/publish/$pubsubName/$topicName", $options);
    }
}
