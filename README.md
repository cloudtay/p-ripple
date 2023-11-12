### install

```bash
composer require cclilshy/p-ripple
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
```

### `Index.php` file

```php 
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
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param PDOProxyPool $PDOProxyPool  PDO代理池
     * @param House $house 模拟依赖注入的对象
     * @param Blade $blade 实现依赖注入的对象
     * @return Generator 返回一个生成器
     */
    public static function index(Request $request, PDOProxyPool $PDOProxyPool, House $house, Blade $blade): Generator
    {
        yield Response::new(200, [], 'the path is ' . $house->getPath());

        $request->async(Request::EVENT_UPLOAD, function (array $info) {

        });

        $request->await();
    }
}

```

#### start

```bash
php main.php
```

#### show

> `http://127.0.0.1:8008`

### ......
