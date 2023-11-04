### install

```bash
composer require cclilshy/p-ripple
```

### create the main file

```bash
vim main.php
``` 

```php
<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\App\Http\Http;
use Cclilshy\PRipple\App\Http\Request;
use Cclilshy\PRipple\App\Http\Response;
use Cclilshy\PRipple\PRipple;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();

$options = [SO_REUSEPORT => true];

$tcp = TestTCP::new('tcp_worker_name')->bind('tcp://127.0.0.1:3001', $options);
$ws = TestWS::new('ws_worker_name')->bind('tcp://127.0.0.1:3002', $options);
$http = Http::new('http_worker_name')
    ->bind('tcp://0.0.0.0:3008', $options)
    ->bind('tcp://127.0.0.1:3009', $options);

$http->defineRequestHandler(function (Request $request) use ($ws, $tcp) {
    if ($request->method === 'GET') {
        yield new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = file_get_contents(__DIR__ . '/example.html')
        );
    } elseif ($request->upload) {
        yield new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'File transfer is in progress, please do not close the page...'
        );

        $request->async(Request::EVENT_UPLOAD, function (array $info) use ($ws, $tcp) {
            foreach ($ws->getClients() as $client) {
                $client->send('file upload completed:' . json_encode($info) . PHP_EOL);
            }

            foreach ($tcp->getClients() as $client) {
                $client->send('file upload completed:' . json_encode($info) . PHP_EOL);
            }
        });

        $request->await();
    } else {
        yield new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "you submitted:" . json_encode($request->post)
        );
    }
});

$pRipple->push($http, $ws, $tcp)->launch();

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

### Process

```php
use Cclilshy\PRipple\App\ProcessManager\Process;

Process::fork(function(){
    Process::fork(function(){
        Process::fork(function(){
            echo "some pid:" . posix_getpid() . PHP_EOL;
        });
    });
});

// anywhere
ProcessManager::instance()->signal('some pid',SIGTERM);
```
