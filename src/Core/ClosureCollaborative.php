<?php

namespace Core;

use Closure;
use Core\Std\CollaborativeFiberStd;

/**
 *
 */
class ClosureCollaborative extends CollaborativeFiberStd
{
    /**
     * @param Closure $callable
     */
    public function __construct(Closure $callable)
    {
        $this->setupWithCallable($callable);
    }
}
