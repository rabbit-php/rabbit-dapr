<?php

namespace Rabbit\Dapr\Client;

/**
 * Class AppId
 * @package Rabbit\Dapr\Client
 */
class AppId
{
    public function __construct(public string $id, public string $namespace = '')
    {
    }

    public function getAddress(): string
    {
        return empty($this->namespace) ? $this->id : "{$this->id}.{$this->namespace}";
    }
}
