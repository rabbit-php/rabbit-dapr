<?php

namespace Rabbit\Dapr\Client;

use Rabbit\Dapr\consistency\Consistency;
use Rabbit\Dapr\consistency\EventualFirstWrite;
use Rabbit\Dapr\State\StateItem;

/**
 * Trait HttpStateTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpStateTrait
{
    public function getState(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency $consistency = null,
        array $metadata = []
    ): mixed {
        return $this->getStateAndEtagExt($storeName, $key, $asType, $consistency, $metadata)['value'];
    }

    public function getStateAndEtagExt(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency $consistency = null,
        array $metadata = []
    ): mixed {
        $options = [];
        $metadata = array_merge(
            ...array_map(fn ($key, $value) => ["metadata.$key" => $value], array_keys($metadata), $metadata)
        );
        if (!empty($consistency)) {
            $options['consistency'] = $consistency->get_consistency();
            $options['concurrency'] = $consistency->get_concurrency();
        }
        $options = array_merge($options, $metadata);
        $storeName = rawurlencode($storeName);
        $key = rawurlencode($key);
        $response = $this->httpClient->get(
            "/v1.0/state/$storeName/$key",
            [
                'query' => $options
            ]
        );
        return [
            'value' => $this->deserializer->from_json(
                $asType,
                $response->getBody()->getContents()
            ),
            'etag' => $response->getHeader('Etag')[0] ?? ''
        ];
    }

    public function saveState(
        string $storeName,
        string $key,
        mixed $value,
        ?Consistency $consistency = null,
        array $metadata = []
    ): void {
        $item = new StateItem($key, $value, $consistency, null, $metadata);
        $storeName = rawurlencode($storeName);
        $this->httpClient->post(
            "/v1.0/state/$storeName",
            [
                'json' => [$item]
            ]
        );
    }

    public function saveBulkState(string $storeName, array $stateItems): bool
    {
        $storeName = rawurlencode($storeName);
        return $this->httpClient->post("/v1.0/state/$storeName", ['body' => $this->serializer->as_json($stateItems)])->getStatusCode() === 200;
    }

    public function trySaveState(
        string $storeName,
        string $key,
        mixed $value,
        string $etag,
        ?Consistency $consistency = null,
        array $metadata = []
    ): bool {
        $item = new StateItem($key, $value, $consistency ?? new EventualFirstWrite(), $etag, $metadata);
        $storeName = rawurlencode($storeName);
        try {
            $this->httpClient->post(
                "/v1.0/state/$storeName",
                [
                    'json' => [$item]
                ]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getStateAndEtag(
        string $storeName,
        string $key,
        string $asType = 'array',
        ?Consistency $consistency = null,
        array $metadata = []
    ): array {
        return $this->getStateAndEtagExt($storeName, $key, $asType, $consistency, $metadata)->wait();
    }

    public function executeStateTransaction(string $storeName, array $operations, array $metadata = []): void
    {
        $options = [
            'json' => [
                'operations' => array_map(
                    fn ($operation) => [
                        'operation' => $operation->operationType,
                        'request' => array_merge(
                            [
                                'key' => $operation->key,
                            ],
                            $operation instanceof UpsertTransactionRequest ? ['value' => $operation->value] : [],
                            empty($operation->etag) ? [] : ['etag' => $operation->etag],
                            empty($operation->metadata) ? [] : ['metadata' => $operation->metadata],
                            empty($operation->consistency) || empty($operation->etag) ? [] : [
                                'options' => [
                                    'consistency' => $operation->consistency->get_consistency(),
                                    'concurrency' => $operation->consistency->get_concurrency(),
                                ],
                            ],
                        ),
                    ],
                    $operations
                ),
                'metadata' => $metadata,
            ],
        ];
        $storeName = rawurlencode($storeName);
        $this->httpClient->post("/v1.0/state/$storeName/transaction", $options);
    }

    public function deleteState(
        string $storeName,
        string $key,
        Consistency $consistency = null,
        array $metadata = []
    ): void {
        $this->tryDeleteStateExt($storeName, $key, null, $consistency, $metadata);
    }

    public function tryDeleteStateExt(
        string $storeName,
        string $key,
        ?string $etag,
        Consistency $consistency = null,
        array $metadata = []
    ): bool {
        $consistency ??= new EventualFirstWrite();
        $storeName = rawurlencode($storeName);
        $key = rawurlencode($key);
        try {
            $this->httpClient->delete(
                "/v1.0/state/$storeName/$key",
                array_merge(
                    [
                        'query' => empty($consistency) ? [] : [
                            'consistency' => $consistency->get_consistency(),
                            'concurrency' => $consistency->get_concurrency(),
                        ],
                    ],
                    empty($etag) ? [] : [
                        'headers' => [
                            'If-Match' => $etag
                        ]
                    ]
                )
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function tryDeleteState(
        string $storeName,
        string $key,
        string $etag,
        Consistency $consistency = null,
        array $metadata = []
    ): bool {
        return $this->tryDeleteStateExt($storeName, $key, $etag, $consistency, $metadata);
    }

    public function getBulkState(string $storeName, array $keys, int $parallelism = 10, array $metadata = []): array
    {
        $response = $this->httpClient->get(
            "/v1.0/state/$storeName/bulk",
            [
                'json' => [
                    'keys' => $keys,
                    'parallelism' => $parallelism
                ],
                'query' => array_merge(
                    ...array_map(fn ($key, $value) => ["metadata.$key" => $value], array_keys($metadata), $metadata)
                )
            ]
        );
        return array_merge(
            ...array_map(
                fn (array $result): array => [
                    $result['key'] => [
                        'value' => $result['data'] ?? null,
                        'etag' => $result['etag'] ?? null
                    ]
                ],
                (array)$this->deserializer->from_json('array', $response->getBody()->getContents())
            )
        );
    }
}
