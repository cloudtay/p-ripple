<?php
include __DIR__ . '/vendor/autoload.php';

use PRipple\App\Http\HttpWorker;
use PRipple\App\Http\Request;
use PRipple\App\Http\Response;
use PRipple\App\ProcessManager\ProcessContainer;
use PRipple\App\ProcessManager\ProcessManager;
use PRipple\PRipple;
use PRipple\Protocol\CCL;

$pRipple = $kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$processManager = ProcessManager::new('ProcessManager')
    ->bind('unix://' . ProcessManager::$UNIX_PATH)
    ->protocol(CCL::class);
$http = HttpWorker::new('http_worker_name')->bind('tcp://0.0.0.0:8001', [SO_REUSEPORT => true]);
$http->defineRequestHandler(function (Request $request) {
    $response = new Response(
        $statusCode = 200,
        $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        $body = 'hello,world'
    );
    $request->client->send($response);
    ProcessContainer::fork(function () {
        ProcessContainer::fork(function () {
            echo 'child process' . PHP_EOL;
        });
        ProcessContainer::fork(function () {
            echo 'child process' . PHP_EOL;
        });
        ProcessContainer::guarded();
    });
});

$pRipple->push($processManager, $http)->launch();
