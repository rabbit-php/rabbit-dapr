<?php

namespace Rabbit\Dapr\Actors\Generators;

use Rabbit\Dapr\Actors\IActor;
use Rabbit\Dapr\Client\DaprClient;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\Method;

/**
 * Class ExistingOnly
 *
 * Only allows existing proxies to be used. Does not generate a proxy.
 *
 * @package Rabbit\Dapr\Actors\Generators
 */
class ExistingOnly extends GenerateProxy
{
    #[Pure] public function __construct(
        string $interface,
        string $dapr_type,
        DaprClient $client
    ) {
        parent::__construct($interface, $dapr_type, $client);
    }

    /**
     * @param string $id
     *
     * @return IActor|mixed
     */
    public function get_proxy(string $id)
    {
        $reflection = new \ReflectionClass($this->get_full_class_name());
        $proxy = $reflection->newInstance($this->client);
        $proxy->id = $id;

        return $proxy;
    }

    /**
     * @codeCoverageIgnore Never happens
     * @param Method $method
     */
    protected function generate_failure_method(Method $method): void
    {
        throw new LogicException();
    }

    /**
     * @codeCoverageIgnore Never happens
     * @param Method $method
     * @param string $id
     */
    protected function generate_proxy_method(Method $method, string $id): void
    {
        throw new LogicException();
    }

    /**
     * @codeCoverageIgnore Never happens
     * @param Method $method
     * @param string $id
     */
    protected function generate_get_id(Method $method, string $id): void
    {
        throw new LogicException();
    }
}
