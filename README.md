### install

```bash
composer require cclilshy/p-ripple dev-main
```

### example

> The following provides a sample code and deployment process to demonstrate how it works as a service

```bash
vim main.php
``` 

```php
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

$httpWorker = HttpWorker::new('http')->bind('tcp://127.0.0.1:8008', $options);

WebApplication::inject($httpWorker, $router);

# 使用内置的PDO代理池Worker
$pdoProxyWorker = PDOProxyPool::instance();

# 使用代理池的标准方法创建一个默认的PDO代理
$defaultProxyWorker = $pdoProxyWorker->add('DEFAULT', [
    'dsn'      => 'mysql:host=127.0.0.1;dbname=lav',
    'username' => 'root',
    'password' => '123456',
    'options'  => [],
]);

# 启动2个PDO序列化代理
$defaultProxyWorker->activate(2);

# 启动服务
$kernel->push($httpWorker, $wsWorker)->launch();

```

### File `Index.php` 

```php
<?php

namespace Tests;

use App\Facade\PDOPool;
use App\Http\Request;
use App\PDOProxy\Exception\RollbackException;
use App\PDOProxy\PDOProxyPool;
use App\PDOProxy\PDOTransaction;
use App\WebApplication\Plugins\Blade;
use App\WebApplication\Route;
use Core\Map\WorkerMap;
use Generator;

class Index
{
    /**
     * @param Request      $request      实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param PDOProxyPool $PDOProxyPool 内置Worker都支持自动依赖注入
     * @return Generator 返回一个生成器
     */
    public static function index(Request $request, PDOProxyPool $PDOProxyPool): Generator
    {
        /**
         * 在发生异步操作之前,全局的静态属性都是安全的,但不建议这么做
         * 你可以通过依赖中间件+依赖注入的特性,在中间件或其他地方-
         * 构建你需要的对象如 Session|Cache 或将Cookie注入你的无状态Service等
         */
        yield $request->respondBody('hello world');

        $data = $PDOProxyPool->get('DEFAULT')->query('select * from user where id = ?', [17]);

        /**
         * 你可以通过 WorkerMap::get 获取已经启动的Worker
         * 内置的Worker都是单例模式运行并以className命名
         * @var TestWs $ws
         */
        $ws = WorkerMap::get('ws');
        foreach ($ws->getClients() as $client) {
            $client->send("用户{$request->client->getAddress()} 访问了网站,取得数据:" . json_encode($data));
        }
    }


    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param Blade   $blade   中间件实现的依赖注入
     * @return Generator 返回一个生成器
     */
    public static function upload(Request $request, Blade $blade): Generator
    {
        if ($request->method === Route::GET) {
            yield $request->respondBody($blade->render('upload', [
                'title' => 'upload files'
            ]));
        } elseif ($request->upload) {
            yield $request->respondBody('文件上传中,请勿关闭页面.');

            $request->async(Request::EVENT_UPLOAD, function (array $info) {
                /**
                 * @var TestWs $ws
                 */
                $ws = WorkerMap::get('ws');
                foreach ($ws->getClients() as $client) {
                    $client->send('文件上传成功:' . json_encode($info));
                }
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
        yield $request->respondFile(__DIR__ . '/Index.php', 'index.php');
        $request->await();
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function data(Request $request): Generator
    {
        /**
         * PDOPool::class是PDOProxyPool的助手类,你可以通过静态方法操作代理池单例
         */
        $originData = PDOPool::get('DEFAULT')->query('select * from user where id = ?', [17]);

        /**
         * 你也可以直接使用 PDOProxyPool::class 提供的 instance 方法获取代理池单例
         * 下面模拟了一次事务回滚
         */
        $pdoWorker = PDOProxyPool::instance()->get('DEFAULT');

        $pdoWorker->transaction(function (PDOTransaction $transaction) use (&$updateData) {
            $transaction->query('update user set `username` = ? where `id` = ?', ['changed', 17], []);
            $updateData = $transaction->query('select * from `user` where id = ?', [17], []);

            // 抛出异常
            throw new RollbackException('');
        });

        $resultData = $pdoWorker->query('select * from user where id = ?', [17]);

        yield $request->respondJson([
            'origin' => $originData,
            'update' => $updateData,
            'result' => $resultData,
        ]);
    }
}

```

### 路由 `GET` `/data` 的输出结果

```json
{
  "origin": [
    {
      "id": 17,
      "0": 17,
      "username": "user2",
      "1": "user2",
      "password": "password2",
      "2": "password2",
      "level_id": 2,
      "3": 2,
      "avatar": "avatar2.jpg",
      "4": "avatar2.jpg",
      "nickname": "Nickname 2",
      "5": "Nickname 2",
      "email": "user2@example.com",
      "6": "user2@example.com",
      "phone_code": null,
      "7": null,
      "phone_number": "9876543210",
      "8": "9876543210",
      "type": "type2",
      "9": "type2",
      "create_time": "2023-09-08 11:17:33",
      "10": "2023-09-08 11:17:33",
      "update_time": "2023-09-08 03:27:47",
      "11": "2023-09-08 03:27:47",
      "status": 0,
      "12": 0,
      "delete_time": "2023-09-08 03:27:47",
      "13": "2023-09-08 03:27:47"
    }
  ],
  "update": [
    {
      "id": 17,
      "0": 17,
      "username": "changed",
      "1": "changed",
      "password": "password2",
      "2": "password2",
      "level_id": 2,
      "3": 2,
      "avatar": "avatar2.jpg",
      "4": "avatar2.jpg",
      "nickname": "Nickname 2",
      "5": "Nickname 2",
      "email": "user2@example.com",
      "6": "user2@example.com",
      "phone_code": null,
      "7": null,
      "phone_number": "9876543210",
      "8": "9876543210",
      "type": "type2",
      "9": "type2",
      "create_time": "2023-09-08 11:17:33",
      "10": "2023-09-08 11:17:33",
      "update_time": "2023-11-14 10:19:15",
      "11": "2023-11-14 10:19:15",
      "status": 0,
      "12": 0,
      "delete_time": "2023-09-08 03:27:47",
      "13": "2023-09-08 03:27:47"
    }
  ],
  "result": [
    {
      "id": 17,
      "0": 17,
      "username": "user2",
      "1": "user2",
      "password": "password2",
      "2": "password2",
      "level_id": 2,
      "3": 2,
      "avatar": "avatar2.jpg",
      "4": "avatar2.jpg",
      "nickname": "Nickname 2",
      "5": "Nickname 2",
      "email": "user2@example.com",
      "6": "user2@example.com",
      "phone_code": null,
      "7": null,
      "phone_number": "9876543210",
      "8": "9876543210",
      "type": "type2",
      "9": "type2",
      "create_time": "2023-09-08 11:17:33",
      "10": "2023-09-08 11:17:33",
      "update_time": "2023-09-08 03:27:47",
      "11": "2023-09-08 03:27:47",
      "status": 0,
      "12": 0,
      "delete_time": "2023-09-08 03:27:47",
      "13": "2023-09-08 03:27:47"
    }
  ]
}
```

#### start

```bash
php main.php
```

#### show

> `http://127.0.0.1:8008`

### ......
