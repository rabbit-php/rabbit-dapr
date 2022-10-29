<?php

namespace Rabbit\Dapr\Client;

/**
 * Trait HttpSecretsTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpSecretsTrait
{
    public function getSecret(string $storeName, string $key, array $metadata = []): array|null
    {
        $response = $this->httpClient->get(
            "/v1.0/secrets/$storeName/$key",
            [
                'query' => array_merge(
                    ...array_map(
                        fn ($key, $value) => ["metadata.$key" => $value],
                        array_keys($metadata),
                        $metadata
                    )
                )
            ]
        );
        return (array)$this->deserializer->from_json(
            'array',
            $response->getBody()->getContents()
        );
    }

    public function getBulkSecret(string $storeName, array $metadata = []): array
    {
        $storeName = rawurlencode($storeName);
        $response = $this->httpClient->get(
            "/v1.0/secrets/$storeName/bulk",
            [
                'query' => array_merge(
                    ...array_map(
                        fn ($key, $value) => ["metadata.$key" => $value],
                        array_keys($metadata),
                        $metadata
                    )
                )
            ]
        );
        return (array)$this->deserializer->from_json(
            'array',
            $response->getBody()->getContents()
        );
    }
}
