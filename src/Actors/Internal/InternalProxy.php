<?php

namespace Rabbit\Dapr\Actors\Internal;

use Rabbit\Dapr\Actors\ActorTrait;

/**
 * Class InternalProxy
 * @package Dapr
 */
class InternalProxy
{
    use ActorTrait;

    /**
     * Proxies calls to the proxy
     *
     * @param string $name The name of the method to call
     * @param array $arguments Arguments to pass to the method
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return call_user_func_array($this->$name, $arguments);
    }
}
