<?php

namespace Rabbit\Dapr\Actors;

/**
 * Class ReentrantConfig
 * @package Rabbit\Dapr\Actors
 */
class ReentrantConfig
{
    public function __construct(public int|null $max_stack_depth = null)
    {
    }
}
