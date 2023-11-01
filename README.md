### install

```bash
composer require cclilshy/p-ripple
```

### create main file

```bash
vim main.php
``` 

```php
<?php

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\App\Http\Http;
use Cclilshy\PRipple\App\Http\Request;
use Cclilshy\PRipple\App\Http\Response;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\WebSocket;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();

$options = [SO_REUSEPORT => true];

$tcp = TestTcp::new('tcp_worker_name')->bind('tcp://127.0.0.1:3001', $options);
$ws = TestWS::new('ws_worker_name')->bind('tcp://127.0.0.1:3002', $options)->protocol(WebSocket::class);
$http = Http::new('http_worker_name')->bind('tcp://127.0.0.1:3008', $options);

$http->defineRequestHandler(function (Request $request) use ($tcp, $ws) {
    if ($request->method === 'GET') {
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = file_get_contents(__DIR__ . '/example.html')
        );
    } elseif ($request->upload) {
        $request->handleUpload(function ($info) use ($tcp, $ws) {
            foreach ($ws->getClients() as $client) {
                $client->send('上传成功:' . json_encode($info));
            }
            foreach ($tcp->getClients() as $client) {
                $client->send('上传成功:' . json_encode($info) . PHP_EOL);
            }
        });
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = '请勿关闭页面,上传中...'
        );
        $request->wait();
    } else {
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'You submitted:' . json_encode($request->post)
        );
    }
    $request->client->send($response);
});

$pRipple->push($tcp, $ws, $http)->launch();

```

### create template file

```bash
vim example.html
```

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>File upload form example</title></head>
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

### run

```bash
php main.php
```

### show

> `http://127.0.0.1:3008`
