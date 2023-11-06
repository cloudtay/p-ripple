### install

```bash
composer require cclilshy/p-ripple
```

### what is it?

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
    delay(3); #Delay execution for 3 seconds
    echo 'hello,world' . PHP_EOL;
});

async(function () {
    delay(3); #Delay execution for 3 seconds
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

# You can send a signal to any process anywhere if you know
// signal($someProcessId, SIGTERM);

# Create an async loop
loop(1, function () {
    echo 'loop' . PHP_EOL;
});

$master->launch();

```

### how to use it?

> The following provides sample code and deployment process to demonstrate how it works as a service

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
            $body = 'File transfer is in progress, please do not close the page...'
        );

        $request->async(Request::EVENT_UPLOAD, function (array $info) use ($ws, $tcp) {
            foreach ($ws->getClients() as $client) {
                $client->send('fileUploadCompleted:' . json_encode($info) . PHP_EOL);
            }

            foreach ($tcp->getClients() as $client) {
                $client->send('fileUploadCompleted:' . json_encode($info) . PHP_EOL);
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

> `http://127.0.0.1:3008`

### ......
