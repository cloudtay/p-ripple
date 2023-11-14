<?php
include __DIR__ . '/vendor/autoload.php';

use App\Http\HttpWorker;
use App\PDOProxy\PDOProxyPool;
use App\WebApplication\Route;
use App\WebApplication\RouteMap;
use App\WebApplication\WebApplication;
use Protocol\WebSocket;
use Tests\Index;
use Tests\TestWs;

//'VIEW_PATH_BLADE'  => __DIR__,
$kernel = PRipple::configure([
    'RUNTIME_PATH'     => '/tmp',
    'HTTP_UPLOAD_PATH' => '/tmp',
    'PP_RUNTIME_PATH'  => '/tmp'
]);

$options = [SO_REUSEPORT => 1];

# 构建WebSocketWorker
$wsWorker = TestWs::new('ws')->bind('tcp://127.0.0.1:8001', $options)->protocol(WebSocket::class);

# 构建HttpWorker并使用注入框架
$router = new RouteMap;
$router->define(Route::GET, '/', [Index::class, 'index'])->middlewares([]);
$router->define(Route::GET, '/download', [Index::class, 'download']);
$router->define(Route::GET, '/upload', [Index::class, 'upload']);
$router->define(Route::POST, '/upload', [Index::class, 'upload']);
$router->define(Route::GET, '/data', [Index::class, 'data']);
$router->define(Route::GET, '/orm', [Index::class, 'orm']);

$httpWorker = HttpWorker::new('http')->bind('tcp://127.0.0.1:8008', $options);

WebApplication::inject($httpWorker, $router);

# 使用内置的PDO代理池Worker
$pdoProxyWorker = PDOProxyPool::instance();

# 使用代理池的标准方法创建一个默认的PDO代理
$defaultProxyWorker = $pdoProxyWorker->add([
    'driver'    => 'mysql',
    'charset'   => 'utf8',
    'hostname'  => '127.0.0.1',
    'database'  => 'lav',
    'username'  => 'root',
    'password'  => '123456',
    'collation' => 'utf8_general_ci',
    'prefix'    => '',
], 'default');

# 启动2个PDO序列化代理
$defaultProxyWorker->activate(2);

# 启动服务
$kernel->push($httpWorker, $wsWorker)->launch();
