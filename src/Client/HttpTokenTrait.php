<?php

namespace Rabbit\Dapr\Client;

/**
 * Trait HttpTokenTrait
 * @package Rabbit\Dapr\Client
 */
trait HttpTokenTrait
{
    static string|false|null $appToken = false;
    static string|false|null $daprToken = false;

    protected function getAppToken(): string|null
    {
        if (self::$appToken === false) {
            self::$appToken = env('APP_API_TOKEN') ?: null;
        }
        return self::$appToken;
    }

    protected function getDaprToken(): string|null
    {
        if (self::$daprToken === false) {
            self::$daprToken = env('DAPR_API_TOKEN') ?: null;
        }
        return self::$daprToken;
    }
}
