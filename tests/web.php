<?php
include __DIR__ . '/vendor/autoload.php';

use App\Http\HttpWorker;
use App\WebApplication\RouteMap;
use App\WebApplication\WebApplication;
use Tests\Index;

$kernel = PRipple::configure([
    'RUNTIME_PATH'     => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);


$router = new RouteMap;
$router->define(RouteMap::GET, '/index', [Index::class, 'index']);
$router->define(RouteMap::GET, '/hello', [Index::class, 'hello']);


$http = HttpWorker::new('http')->bind('tcp://127.0.0.1:8008', [SO_REUSEPORT => 1]);
WebApplication::inject($http, $router);

$kernel->push($http)->launch();
