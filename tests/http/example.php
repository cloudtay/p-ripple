<?php
include_once __DIR__ . '/../vendor/autoload.php';

use Support\Http\HttpWorker;
use Support\PDOProxy\PDOProxyPool;
use Support\WebApplication\Route;
use Support\WebApplication\RouteMap;
use Support\WebApplication\WebApplication;
use Support\WebSocket\WebSocket;
use Tests\http\Index;
use Tests\rpc\TestWS;
use Worker\Worker;

$kernel = PRipple::configure([]);

$options = [SO_REUSEPORT => 1];

# 构建WebSocketWorker
$wsWorker = TestWs::new('ws')->bind('tcp://127.0.0.1:8001', $options)
    ->protocol(WebSocket::class)
    ->mode(Worker::MODE_INDEPENDENT);

# 初始化PDOProxyPool
PDOProxyPool::init([
    'driver'   => 'mysql',
    'hostname' => '127.0.0.1',
    'database' => 'lav',
    'username' => 'root',
    'password' => '123456',
    'options'  => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
])->run(4);

# 构建HttpWorker
$httpWorker = HttpWorker::new('http')
    ->bind('tcp://127.0.0.1:8008', $options)
    ->mode(Worker::MODE_INDEPENDENT)
    ->thread(10);

# 初始化路由
$GLOBALS['ROUTER'] = $router = new RouteMap;
$router->define(Route::GET, '/', [Index::class, 'index'])->middlewares([]);
$router->define(Route::GET, '/download', [Index::class, 'download']);
$router->define(Route::GET, '/upload', [Index::class, 'upload']);
$router->define(Route::POST, '/upload', [Index::class, 'upload']);
$router->define(Route::GET, '/data', [Index::class, 'data']);
$router->define(Route::GET, '/fork', [Index::class, 'fork']);
$router->define(Route::GET, '/hello', [Index::class, 'hello']);

# 使用WebApplication注入HttpWorker
WebApplication::inject($httpWorker, $router, [
    'HTTP_UPLOAD_PATH' => '/tmp'
]);

# 启动服务
$kernel->push($httpWorker, $wsWorker)->launch();
