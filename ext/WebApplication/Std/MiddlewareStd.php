<?php

namespace recycle\WebApplication\Std;

use recycle\Http\Request;

interface MiddlewareStd
{
    public function handle(Request &$collaborativeFiber): void;
}
