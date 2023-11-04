<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\App\Http\Http;
use Cclilshy\PRipple\App\Http\Request;
use Cclilshy\PRipple\App\Http\Response;
use Cclilshy\PRipple\App\ProcessManager\Process;
use Cclilshy\PRipple\App\ProcessManager\ProcessManager;
use Cclilshy\PRipple\PRipple;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();

$options = [SO_REUSEPORT => true];

$http = Http::new('http_worker_name')
    ->bind('tcp://0.0.0.0:8001', $options)
    ->bind('tcp://127.0.0.1:8002', $options);

$http->defineRequestHandler(function (Request $request) {
    if ($request->method === 'GET') {
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = file_get_contents(__DIR__ . '/example.html')
        );
        Process::fork(function () {
            $pid1 = Process::fork(function () {
                sleep(180);
                echo 'child process' . PHP_EOL;
            });
            $pid2 = Process::fork(function () {
                sleep(180);
                echo 'child process' . PHP_EOL;
            });
            echo "{$pid1},{$pid2}" . PHP_EOL;
            Process::guarded();
        });
    } elseif ($request->upload) {
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'Please do not close the page, uploading is in progress...'
        );
    } else {
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "You n submitted:" . json_encode($request->post)
        );
        if ($processId = $request->post['name'] ?? null) {
            ProcessManager::instance()->signal(intval($processId), SIGTERM);
        }
    }
    $request->client->send($response->__toString());
});

$pRipple->push($http)->launch();
