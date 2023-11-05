<?php
include __DIR__ . '/vendor/autoload.php';

use PRipple\App\Http\Http;
use PRipple\App\Http\Request;
use PRipple\App\Http\Response;
use PRipple\App\ProcessManager\Process;
use PRipple\App\ProcessManager\ProcessManager;
use PRipple\PRipple;
use PRipple\Protocol\CCL;

$pripple = PRipple::instance();

$processManager = ProcessManager::new('ProcessManager')
    ->bind('unix://' . ProcessManager::UNIX_PATH)
    ->protocol(CCL::class);
$http = Http::new('http_worker_name')->bind('tcp://0.0.0.0:8001', [SO_REUSEPORT => true]);
$http->defineRequestHandler(function (Request $request) {
    $response = new Response(
        $statusCode = 200,
        $headers = ['Content-Type' => 'text/html; charset=utf-8'],
        $body = 'hello,world'
    );
    $request->client->send($response);
    Process::fork(function () {
        Process::fork(function () {
            echo 'child process' . PHP_EOL;
        });
        Process::fork(function () {
            echo 'child process' . PHP_EOL;
        });
        Process::guarded();
    });
});

$pripple->push($processManager, $http)->launch();
