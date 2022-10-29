<?php

namespace Rabbit\Dapr\Client;

/**
 * Class BindingRequest
 * @package Rabbit\Dapr\Client
 */
class BindingRequest
{
    public function __construct(
        public string $bindingName,
        public string $operation,
        public string|null $data = null,
        public array $metadata = []
    ) {
    }
}
