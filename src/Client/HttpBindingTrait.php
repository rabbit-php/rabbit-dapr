<?php

namespace Rabbit\Dapr\Client;

use Rabbit\Dapr\exceptions\DaprException;

/**
 * Trait HttpBindingTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpBindingTrait
{
    /**
     * @throws DaprException
     */
    public function invokeBinding(BindingRequest $bindingRequest, string $dataType = 'array'): BindingResponse
    {
        $response = $this->httpClient->put(
            '/v1.0/bindings/' . rawurlencode($bindingRequest->bindingName),
            [
                'json' => [
                    'data' => $bindingRequest->data,
                    'metadata' => $bindingRequest->metadata,
                    'operation' => $bindingRequest->operation
                ]
            ]
        );
        return new BindingResponse(
            $bindingRequest,
            $this->deserializer->from_json($dataType, $response->getBody()->getContents()),
            $response->getHeaders()
        );
    }
}
