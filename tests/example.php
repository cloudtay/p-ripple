<?php
include __DIR__ . '/vendor/autoload.php';

use recycle\Http\HttpWorker;
use recycle\WebApplication\Route;
use recycle\WebApplication\RouteMap;
use recycle\WebApplication\WebApplication;
use Tests\Index;
use Tests\TestTCP;
use Tests\TestWs;

$kernel = PRipple::configure([
    'RUNTIME_PATH'     => '/tmp',
    'HTTP_UPLOAD_PATH' => '/tmp',
    'PP_RUNTIME_PATH'  => '/tmp'
]);

//$router = new RouteMap();
//$router->define(Route::GET, '/', [Index::class, 'index'])->middlewares([]);
//$router->define(Route::GET, '/rpc', [Index::class, 'rpc'])->middlewares([]);
//$router->define(Route::GET, '/download', [Index::class, 'download']);
//$router->define(Route::GET, '/upload', [Index::class, 'upload']);
//$router->define(Route::POST, '/upload', [Index::class, 'upload']);
//$router->define(Route::GET, '/data', [Index::class, 'data']);
//$router->define(Route::GET, '/orm', [Index::class, 'orm']);
//$router->define(Route::GET, '/login', [Index::class, 'login']);
//$httpWorker = HttpWorker::new('http')->bind('tcp://127.0.0.1:8008', ['nonblock' => true, SO_REUSEADDR => 1, SO_REUSEPORT => 1]);

$ws = TestWs::new(TestWs::class)->bind('tcp://127.0.0.1:8001', [
    'nonblock'   => true,
    SO_REUSEPORT => 1
])->protocol(Protocol\WebSocket::class);

$tcp = TestTCP::new(TestTCP::class)->bind('tcp://127.0.0.1:8002', [
    'nonblock'   => true,
    SO_REUSEPORT => 1
]);
$kernel->push($ws, $tcp)->listen();
