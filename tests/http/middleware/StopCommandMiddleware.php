<?php

namespace Tests\http\middleware;

use Generator;
use Support\Http\Request;
use Support\WebApplication\Std\MiddlewareStd;

class StopCommandMiddleware implements MiddlewareStd
{
    /**
     * @param Request $request
     * @return Generator
     */
    public function handle(Request $request): Generator
    {
        if (isset($request->query['stop'])) {
            yield $request->respondBody('stop');
        }
    }
}
