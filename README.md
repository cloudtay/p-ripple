### 项目介绍

太强大了,暂时无法介绍.

### create `main.php`

```php
<?php
include_once __DIR__ . '/../vendor/autoload.php';

use Support\Http\HttpWorker;
use Support\PDOProxy\PDOProxyPool;
use Support\WebApplication\Route;
use Support\WebApplication\RouteMap;
use Support\WebApplication\WebApplication;
use Support\WebSocket\WebSocket;
use Tests\http\Index;
use Worker\Built\JsonRpc\Attribute\RPC;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

$kernel  = PRipple::configure([]);
$options = [
    SO_REUSEPORT => 1,
    SO_REUSEADDR => 1,
    'nonblock'   => 1
];

// 构建一个自定义服务
$ws = new class('ws') extends Worker {
    use JsonRpc;

    /**
     * 发生连接且握手时触发,可以在这里做一些初始化操作如IP白名单拦截等
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
        // TODO: Implement onConnect() method.
    }

    /**
     * 客户端断开连接时触发如清理资源
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
        // TODO: Implement onClose() method.
    }

    /**
     * 握手成功时触发
     * @param TCPConnection $client
     * @return void
     */
    public function onHandshake(TCPConnection $client): void
    {
        $client->send('hello');
    }

    /**
     * 消息到达时触发
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        $client->send('you say: ' . $context);
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }

    /**
     * 心跳触发
     * @return void
     */
    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    /**
     * 一个自定义的RPC方法
     * @param string $message
     * @return void
     */
    #[RPC("发送消息到所有客户端")] public function sendMessageToAll(string $message): void
    {
        foreach ($this->getClients() as $client) {
            $client->send($message);
        }
    }
};

// 将服务绑定Websocket协议并设定为独立运行模式
$ws->bind('tcp://0.0.0.0:8001', $options)->protocol(WebSocket::class)->mode(Worker::MODE_INDEPENDENT);

// 构建数据库连接池服务,内置LaravelORM
$pool = new PDOProxyPool([
    'driver'   => 'mysql',
    'hostname' => '127.0.0.1',
    'database' => 'lav',
    'username' => 'root',
    'password' => '123456',
    'options'  => [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
    ]
]);
$pool->run(4);

# 构建HTTP服务
$httpWorker = HttpWorker::new('http')->bind('tcp://0.0.0.0:8008', $options)->mode(Worker::MODE_INDEPENDENT, 4);
// 为WebApplication应用处理器构建路由并注入到HttpWorker中
$router = new RouteMap();
$router->define(Route::GET, '', [Index::class, 'index']);
$router->define(Route::GET, '/info', [Index::class, 'info']);
$router->define(Route::GET, '/data', [Index::class, 'data']);
$router->define(Route::GET, '/fork', [Index::class, 'fork']);
$router->define(Route::GET, '/notice', [Index::class, 'notice']);
WebApplication::inject($httpWorker, $router, [
    'HTTP_UPLOAD_PATH' => '/tmp',
    'HTTP_PUBLIC'      => __DIR__ . '/public'
]);

PRipple::kernel()->push($pool, $httpWorker, $ws)->launch();

```

### create you Controller `Index.php`

```php
<?php

namespace Tests\http;

use Core\Map\WorkerMap;
use Facade\JsonRpc;
use Generator;
use Illuminate\Support\Facades\DB;
use PRipple;
use Support\Http\Request;

class Index
{
    /**
     * @param Request $request
     * @return Generator
     */
    public static function index(Request $request): Generator
    {
        yield $request->respondBody('hello world');
    }

    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @return Generator 返回一个生成器
     */
    public static function info(Request $request): Generator
    {
        /**
         * 在发生异步操作之前,全局的静态属性都是安全的,但不建议使用静态属性
         * 你可以通过依赖中间件+依赖注入的特性,在中间件或其他地方构建你需要的对象
         * 如 Session|Cache或将Cookie注入你的无状态Service等
         */
        yield $request->respondJson([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'processId'   => posix_getpid(),
                'rpcServices' => array_keys(JsonRpc::getInstance()->rpcServiceConnections),
                'configure'   => PRipple::getArgument()
            ],
        ]);
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function data(Request $request): Generator
    {
        yield $request->respondJson(DB::table('user')->first());
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function fork(Request $request): Generator
    {
        $pid = WorkerMap::get('http')?->fork();
        yield $request->respondJson([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'pid' => $pid
            ],
        ]);
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function notice(Request $request): Generator
    {
        if ($message = $request->query['message'] ?? null) {
            JsonRpc::call('ws', 'sendMessageToAll', $message);
            yield $request->respondJson([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'message' => $message
                ],
            ]);
        } else {
            yield $request->respondJson([
                'code' => 1,
                'msg'  => 'error',
                'data' => [
                    'message' => 'message is required'
                ],
            ]);
        }
    }
}
```
