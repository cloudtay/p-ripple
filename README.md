### 项目介绍

太强大了,暂时无法介绍.

### create `main.php`

```php
<?php
include_once __DIR__ . '/vendor/autoload.php';

use Support\Http\HttpWorker;
use Support\PDOProxy\PDOProxyPool;
use Support\WebApplication\Route;
use Support\WebApplication\RouteMap;
use Support\WebApplication\WebApplication;
use Support\WebSocket\WebSocket;
use Worker\Worker;

$kernel = PRipple::configure([
    'RUNTIME_PATH'     => '/tmp',
    'HTTP_UPLOAD_PATH' => '/tmp',
    'PP_RUNTIME_PATH'  => '/tmp'
]);

$options = [SO_REUSEPORT => 1];

# 构建WebSocketWorker
$wsWorker = TestWS::new('ws')->bind('tcp://127.0.0.1:8001', $options)
    ->protocol(WebSocket::class)
    ->mode(Worker::MODE_INDEPENDENT);

# 构建HttpWorker并使用注入框架
$router = new RouteMap;
$router->define(Route::GET, '/', [Index::class, 'index'])->middlewares([]);
$router->define(Route::GET, '/download', [Index::class, 'download']);
$router->define(Route::GET, '/upload', [Index::class, 'upload']);
$router->define(Route::POST, '/upload', [Index::class, 'upload']);
$router->define(Route::GET, '/data', [Index::class, 'data']);
$httpWorker = HttpWorker::new('http')->bind('tcp://127.0.0.1:8008', $options)->mode(Worker::MODE_INDEPENDENT);
WebApplication::inject($httpWorker, $router, []);

$pool = new PDOProxyPool([
    'driver'   => 'mysql',
    'hostname' => '127.0.0.1',
    'database' => 'lav',
    'username' => 'root',
    'password' => '123456',
    'options'  => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
]);

$pool->run(10);

# 启动服务
$kernel->push($httpWorker, $wsWorker)->launch();

```

### create you Controller `Index.php`

```php
<?php

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Factory;
use Support\Http\Request;
use Support\WebApplication\Route;
use Tests\rpc\TestWS;
use Worker\Built\JsonRpc\JsonRpcClient;

class Index
{
    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @return Generator 返回一个生成器
     */
    public static function index(Request $request): Generator
    {
        /**
         * 在发生异步操作之前,全局的静态属性都是安全的,但不建议使用静态属性
         * 你可以通过依赖中间件+依赖注入的特性,在中间件或其他地方-
         * 构建你需要的对象如 Session|Cache 或将Cookie注入你的无状态Service等
         */
        yield $request->respondBody('hello world');
        $data = DB::table('user')->where('id', 17)->first();

        /**
         * 你可以通过 WorkerMap::get 获取已经启动的Worker
         * 内置的Worker都是单例模式运行并以className命名
         * @var TestWS $ws
         */
        JsonRpcClient::getInstance()->call(
            'ws',
            'sendMessageToClients',
            "data:" . json_encode($data)
        );
    }


    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param Factory $blade   WebApplication实现的依赖注入
     * @return Generator 返回一个生成器
     */
    public static function upload(Request $request, Factory $blade): Generator
    {
        if ($request->method === Route::GET) {
            yield $request->respondBody($blade->make('upload', [
                'title' => 'upload files'
            ])->render());
        } elseif ($request->upload) {
            yield $request->respondBody('文件上传中,请勿关闭页面.');
            $request->async(Request::EVENT_UPLOAD, function (array $info) {
                /**
                 * @var TestWS $ws
                 */
                JsonRpcClient::getInstance()->call(
                    'ws',
                    'sendMessageToClients',
                    '文件上传成功:' . json_encode($info)
                );
            });

            // 一个请求的生命周期直至这个function结束为止,如果你定义了异步事件,请用该方法声明Worker禁止回收
            // 事件的处理器会对该请求的最终回收负责
            $request->await();
        }
    }

    /**
     * 下载文件
     * @param Request $request
     * @return Generator
     */
    public static function download(Request $request): Generator
    {
        yield $request->respondFile(__DIR__ . '/Index.php', 'Index.php');
        $request->await();
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function data(Request $request): Generator
    {
        yield $request->respondJson(DB::table('user')->first());
        // TODO: 自己实现回滚去吧
//        /**
//         * PDOPool::class 是 PDOProxyPool的助手类,你可以直接静态方法操作代理池
//         */
//        $originData = PDOPool::get('DEFAULT')->query('select * from user where id = ?', [17]);
//
//        /**
//         * 你也可以直接使用 PDOProxyPool::class 提供的 instance 方法获取代理池
//         * 下面模拟了一次事务回滚
//         */
//        $pdoWorker = PDOProxyPool::instance()->get('DEFAULT');
//
//        $pdoWorker->transaction(function (PDOTransaction $transaction) use (&$updateData) {
//            $transaction->query('update user set `username` = ? where `id` = ?', ['changed', 17], []);
//            $updateData = $transaction->query('select * from `user` where id = ?', [17], []);
//
//            // 数据回滚
//            throw new RollbackException('');
//        });
//
//        $resultData = $pdoWorker->query('select * from user where id = ?', [17]);
//
//        yield $request->respondJson([
//            'origin' => $originData,
//            'update' => $updateData,
//            'result' => $resultData,
//        ]);
    }
}
```

### create WSService `TestWS.php`

```php
<?php
declare(strict_types=1);

use Worker\Built\JsonRpc\Attribute\RPC;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

class TestWS extends Worker
{
    use JsonRpc;

    /**
     * @return void
     */
    public function heartbeat(): void
    {

    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
    }

    /**
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
    }

    /**
     * @param string $message
     * @return mixed
     */
    #[RPC("向所有客户端发送消息")] public function sendMessageToClients(string $message): mixed
    {
        foreach ($this->getClients() as $client) {
            $client->send($message);
        }
        return true;
    }

    public function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }

    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }
}

```
