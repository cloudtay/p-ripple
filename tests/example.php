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
    }
    $request->client->send($response->__toString());
});

$pRipple->push($http)->launch();
