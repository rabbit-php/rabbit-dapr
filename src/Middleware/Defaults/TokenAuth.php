<?php

namespace Rabbit\Dapr\Middleware\Defaults;

use Rabbit\Dapr\Client\HttpTokenTrait;
use Rabbit\Dapr\exceptions\Http\NotFound;
use Rabbit\Dapr\Middleware\IRequestMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * Class TokenAuth
 * @package Rabbit\Dapr\Middleware\Defaults
 */
class TokenAuth implements IRequestMiddleware
{
    use HttpTokenTrait;

    public function request(RequestInterface $request): RequestInterface
    {
        $token = $this->getAppToken();
        if ($token === null) {
            return $request;
        }

        if (!$request->hasHeader('dapr-api-token')) {
            throw new NotFound();
        }

        if (!hash_equals($token, $request->getHeader('dapr-api-token')[0] ?? '')) {
            throw new NotFound();
        }

        return $request;
    }
}
