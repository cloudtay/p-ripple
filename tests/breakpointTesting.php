<?php
declare(strict_types=1);

use App\Http\HttpWorker;
use App\Http\Request;
use App\Http\Response;
use App\PDOProxy\PDOProxyPool;

include __DIR__ . '/vendor/autoload.php';

$kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$options = [SO_REUSEPORT => true];
$http = HttpWorker::new('http')->bind('tcp://127.0.0.1:8008', $options);
$http->defineRequestHandler(function (Request $request) {
//    delay(1);
//    PDOProxy::query('select * from `user` where `id` = ?', [17], []);
    yield Response::new(
        $statusCode = 200,
        $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        $body = 'hello world'
    );
});
PDOProxyPool::addProxy(1, [
    'dns' => 'mysql:host=127.0.0.1;dbname=lav',
    'username' => 'root',
    'password' => '123456',
    'options' => $options
]);

$kernel->push($http)->launch();
