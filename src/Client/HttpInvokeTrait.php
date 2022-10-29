<?php

namespace Rabbit\Dapr\Client;

use Rabbit\Dapr\exceptions\DaprException;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpInvokeTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpInvokeTrait
{
    /**
     * @throws DaprException
     */
    public function invokeMethod(
        string $httpMethod,
        AppId $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): ResponseInterface {
        $options = [
            'method' => $httpMethod,
            'uri' => sprintf("/v1.0/invoke/%s/method/$methodName", $appId->id)
        ];
        if (!empty($data)) {
            $options['body'] = $this->serializer->as_json($data);
        }
        $options['headers'] = $metadata;
        $appId = rawurlencode($appId->getAddress());
        $methodName = rawurlencode($methodName);
        $methodName = str_replace('%2F', '/', $methodName);
        return $this->httpClient->request($options);
    }
}
