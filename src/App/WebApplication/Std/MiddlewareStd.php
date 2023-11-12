<?php

namespace App\WebApplication\Std;

use App\Http\Request;

interface MiddlewareStd
{
    public function handle(Request &$collaborativeFiber): void;
}
