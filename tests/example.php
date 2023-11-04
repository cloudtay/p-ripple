<?php

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\App\Http\Http;
use Cclilshy\PRipple\App\Http\Request;
use Cclilshy\PRipple\App\Http\Response;
use Cclilshy\PRipple\App\Redis\Redis;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\WebSocket;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();

$options = [SO_REUSEPORT => true];

$tcp = TestTcp::new('tcp_worker_name')->bind('tcp://127.0.0.1:3001', $options);
$ws = TestTcp::new('ws_worker_name')->bind('tcp://127.0.0.1:3002', $options)->protocol(WebSocket::class);
$http = Http::new('http_worker_name')
    ->bind('tcp://0.0.0.0:8001', $options)
    ->bind('tcp://127.0.0.1:8002', $options);
$redis = Redis::new('redis_worker_name')->authorize(1, 1, 1, 1);

$http->defineRequestHandler(function (Request $request) use ($tcp, $ws, $redis) {
//    foreach ($tcp->getClients() as $client) {
//        $client->send("Access [{$request->client->getAddress()}] :{$request->method} {$request->path}" . PHP_EOL);
//    }
    if ($request->method === 'GET') {
//        $response = new Response(
//            $statusCode = 200,
//            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
//            $body = file_get_contents(__DIR__ . '/example.html')
//        );
//        $name = $redis->get('name');
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "hello,world!"
        );
    } elseif ($request->upload) {
        $request->publishAsync(Request::EVENT_UPLOAD, function ($info) use ($tcp, $ws) {
            foreach ($tcp->getClients() as $client) {
                $client->send("Upload complete: " . json_encode($info) . PHP_EOL);
            }
            foreach ($ws->getClients() as $client) {
                $client->send("Upload complete: " . json_encode($info) . PHP_EOL);
            }
        });
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'Please do not close the page, uploading is in progress...'
        );
    } else {
        $name = $redis->get('name');
        $response = new Response(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "Hello,{$name}! You n submitted:" . json_encode($request->post)
        );
    }
    $request->client->send($response);
});

$pRipple->push($tcp, $ws, $http, $redis)->launch();
