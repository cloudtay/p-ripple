<?php
include __DIR__ . '/vendor/autoload.php';

use Cclilshy\PRipple\App\Http\Http;
use Cclilshy\PRipple\App\Http\Request;
use Cclilshy\PRipple\App\Http\Response;
use Cclilshy\PRipple\App\ProcessManager\Process;
use Cclilshy\PRipple\App\ProcessManager\ProcessManager;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\CCL;

$pripple = PRipple::instance();

$processManager = ProcessManager::new('ProcessManager')
    ->bind('unix:///tmp/pripple_process_manager.sock')
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
