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
$options = [SO_REUSEPORT => 1];

# define ws
$ws = TestWs::new('ws')->bind('tcp://127.0.0.1:3009',$options)->protocol(WebSocket::class);

# define http
$http = Http::new('http')->bind('tcp://127.0.0.1:3008',$options);
$http->defineRequestHandler(function (Request $request) use ($ws) {
    $response = new Response(
        $statusCode = 200,
        $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        $body = 'ws online users: ' . count($ws->getClients())
    );
    $request->client->send($response);
});

$pRipple->push($ws,$http)->launch();
```

### run

```bash
php main.php
```

### show

> `http://127.0.0.1:3008`
