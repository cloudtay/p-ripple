<?php

namespace Tests;

use App\Http\Request;
use Generator;

class Index
{
    /**
     * @param Request $request
     * @return Generator
     */
    public static function index(Request $request): Generator
    {
        yield $request->respondBody('hello,world');
        $request->await();
    }
}
