<?php

namespace Tests;

use App\Http\Request;
use App\Http\Response;
use App\PDOProxy\PDOProxyPool;
use App\WebApplication\Plugins\Blade;
use Generator;
use Throwable;

class Index
{
    /**
     * @param Request $request
     * @param PDOProxyPool $PDOProxyPool
     * @param House $house
     * @param Blade $blade
     * @return Generator
     */
    public static function index(Request $request, PDOProxyPool $PDOProxyPool, House $house, Blade $blade): Generator
    {
        yield Response::new(200, [], 'the path is ' . $house->getPath());

        $request->async(Request::EVENT_UPLOAD, function (array $info) {

        });

        $request->await();
    }
}
