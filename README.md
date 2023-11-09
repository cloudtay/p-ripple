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
declare(strict_types=1);

namespace Tests;

use App\Facade\PDOProxy;
use App\Http\HttpWorker;
use App\Http\Request;
use App\Http\Response;
use PRipple;
use Protocol\WebSocket;
use function PRipple\delay;

include __DIR__ . '/vendor/autoload.php';

$kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$options = [SO_REUSEPORT => true];

# 创建一个WebSocket服务
$ws = TestWS::new('ws_worker_name')
    ->bind('tcp://127.0.0.1:8010', $options)
    ->protocol(WebSocket::class);

# 创建一个TCP服务
$tcp = TestTCP::new('tcp_worker_name')
    ->bind('tcp://127.0.0.1:8011', $options);

# 创建一个HTTP服务
$http = HttpWorker::new('http_worker_name')
    ->bind('tcp://0.0.0.0:8008', $options)
    ->bind('tcp://127.0.0.1:8009', $options);

# 声明HTTP请求处理器
$http->defineRequestHandler(function (Request $request) use ($ws, $tcp) {
    if ($request->method === 'GET') {
        # 直接返回该请求
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = file_get_contents(__DIR__ . '/example.html')
        );

        // 查询数据库
        $result = PDOProxy::query('select * from user where id = ?', [17], []);

        // 延时一秒后向所有客户端发送数据查询结果
        delay(1);
        foreach ($ws->getClients() as $client) {
            $client->send('取得数据: ' . json_encode($result));
        }

        foreach ($tcp->getClients() as $client) {
            $client->send('取得数据: ' . json_encode($result) . PHP_EOL);
        }
    } elseif ($request->upload) {
        // 在上传完成前响应客户请求
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'File transfer is in progress, please do not close the page...'
        );

        // 定义上传完成处理器,分别向所有WebSocket客户端和TCP客户端发送文件上传完成的信息
        $request->async(Request::EVENT_UPLOAD, function (array $info) use ($ws, $tcp) {
            foreach ($ws->getClients() as $client) {
                $client->send('file upload completed:' . json_encode($info) . PHP_EOL);
            }
            foreach ($tcp->getClients() as $client) {
                $client->send('file upload completed:' . json_encode($info) . PHP_EOL);
            }
        });

        // Http服务禁止回收该请求
        $request->await();
    } else {
        // POST请求
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "you submitted:" . json_encode($request->post)
        );
    }
});

# 定义HTTP错误处理器
//  `defineRequestHandler` 定义的处理器在执行过程中发生错误将会触发该处理器
//  `defineExceptionHandler` 内不建议做太多复杂的处理, 因为触发该方法的前提是处理器内部发生了错误
$http->defineExceptionHandler(function (mixed $error, Request $request) {
    $response = Response::new(
        $statusCode = 500,
        $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        $body = 'Internal Server Error:' . $error->getMessage()
    );
    $request->client->send($response->__toString());
});

# PDO代理池新增一个代理(详见文档:PDO代理),支持普通查询/事务查询
PDOProxy::addProxy(1, [
    'dns' => 'mysql:host=127.0.0.1;dbname=lav',
    'username' => 'root',
    'password' => '123456',
    'options' => $options
]);

# 启动服务
$kernel->push($http, $ws, $tcp)->launch();
```

#### Create template file

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

#### start

```bash
php main.php
```

#### show

> `http://127.0.0.1:8008`

### ......
