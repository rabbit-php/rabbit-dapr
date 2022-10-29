<?php

namespace Rabbit\Dapr\Middleware\Defaults\Response;

use Rabbit\Dapr\Middleware\IResponseMiddleware;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApplicationJson
 * @package Rabbit\Dapr\Middleware\Defaults\Response
 */
class ApplicationJson implements IResponseMiddleware
{
    public function response(ResponseInterface $response): ResponseInterface
    {
        if ($response->hasHeader('Content-Type')) {
            return $response;
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
