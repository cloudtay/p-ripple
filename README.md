### 安装

```bash
composer require cclilshy/p-ripple
```

### 它是什么?

```php
<?php
include __DIR__ . '/vendor/autoload.php';

use PRipple\PRipple;
use function Cclilshy\PRipple\async;
use function Cclilshy\PRipple\delay;
use function Cclilshy\PRipple\fork;
use function Cclilshy\PRipple\loop;

$master = PRipple::instance()->initialize();

async(function () {
    delay(3); #延时3秒执行
    echo 'hello,world' . PHP_EOL;
});

async(function () {
    delay(3); #延时3秒执行
    echo 'hello,world' . PHP_EOL;
});

async(function () {
    fork(function () {
        fork(function () {
            $someProcessId = fork(function () {
                echo 'child process' . PHP_EOL;
            });
            echo "someProcessId: {$someProcessId} " . PHP_EOL;
        });
    });
});

# 如果你知道的话,你可以在任何地方向任何进程发送信号
// signal($someProcessId, SIGTERM);

# 创建一个异步循环
loop(1, function () {
    echo 'loop' . PHP_EOL;
});

$master->launch();

```

### 能做什么?

> 以下提供了例子代码及部署流程以演示它作为服务时如何工作

```bash
vim main.php
``` 

```php
<?php
declare(strict_types=1);

namespace PRipple\Tests;

use PRipple\App\Http\Http;
use PRipple\App\Http\Request;
use PRipple\App\Http\Response;
use PRipple\PRipple;
use PRipple\Protocol\WebSocket;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance()->initialize();

$options = [SO_REUSEPORT => true];
$http = Http::new('http_worker_name')
    ->bind('tcp://0.0.0.0:8008', $options)
    ->bind('tcp://127.0.0.1:8009', $options);
$ws = TestWS::new('ws_worker_name')->bind('tcp://127.0.0.1:8010', $options)->protocol(WebSocket::class);
$tcp = TestTCP::new('tcp_worker_name')->bind('tcp://127.0.0.1:8011', $options);

$http->defineRequestHandler(function (Request $request) use ($ws, $tcp) {
    if ($request->method === 'GET') {
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = file_get_contents(__DIR__ . '/example.html')
        );
    } elseif ($request->upload) {
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = '文件传输正在进行中，请勿关闭页面...'
        );

        $request->async(Request::EVENT_UPLOAD, function (array $info) use ($ws, $tcp) {
            foreach ($ws->getClients() as $client) {
                $client->send('文件上传完成:' . json_encode($info) . PHP_EOL);
            }

            foreach ($tcp->getClients() as $client) {
                $client->send('文件上传完成:' . json_encode($info) . PHP_EOL);
            }
        });
        $request->await();
    } else {
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "you submitted:" . json_encode($request->post)
        );
    }
});

$pRipple->push($http, $ws, $tcp)->launch();
```

#### 创建模板文件

```bash
vim example.html
```

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Example</title></head>
<body><h1>File upload example</h1>
<form action="/" enctype="multipart/form-data" method="post"><label for="file">Select file to upload：</label>
    <input id="file" name="file" type="file"><br><br> <input type="submit" value="Upload">
</form>
<form action="/" method="POST">
    <input name="name" type="text" value="test"> <input name="age" type="text" value="18">
    <input type="submit" value="提交">
</form>
</body>
</html>
```

#### 启动

```bash
php main.php
```

#### 查看效果

> `http://127.0.0.1:3008`

### ......
