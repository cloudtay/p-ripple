<?php

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\App\Http;
use Cclilshy\PRipple\App\Http\Response;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\WebSocket;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();
$http = Http::new('http')->bind('tcp://127.0.0.1:3008');
$ws = TestWs::new('ws')->bind('tcp://127.0.0.1:3002')->protocol(WebSocket::class);

$http->defineRequestHandler(function (Http\Request $request) {
    $response = new Response(
        $statusCode = 200,
        $headers = ['Content-Type' => 'text/html; charset=utf-8',],
        $body = file_get_contents(__DIR__ . '/example.html')
    );

    if ($request->upload) {
        $request->onUpload(function ($info) {
            echo json_encode($info, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        });
        $request->wait();
    }
    $request->client->send($response);
});

$pRipple->push($http, $ws)->push();
$pRipple->launch();