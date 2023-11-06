<?php
declare(strict_types=1);

namespace PRipple\Tests;

use PRipple\App\Http\Http;
use PRipple\App\Http\Request;
use PRipple\App\Http\Response;
use PRipple\PRipple;
use PRipple\Protocol\WebSocket;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();

$options = [SO_REUSEPORT => true];
$http = Http::new('http_worker_name')
    ->bind('tcp://0.0.0.0:8008', $options)
    ->bind('tcp://127.0.0.1:8009', $options);
$ws = TestWS::new('ws_worker_name')->bind('tcp://127.0.0.1:8010', $options)->protocol(WebSocket::class);
$tcp = TestTCP::new('tcp_worker_name')->bind('tcp://127.0.0.1:8011', $options);

$http->defineRequestHandler(function (Request $request) use ($ws, $tcp) {
    if ($request->method === 'GET') {
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'hello world'
        );
    } elseif ($request->upload) {
        yield Response::new(
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
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "you submitted:" . json_encode($request->post)
        );
    }
});

$pRipple->push($http, $ws, $tcp)->launch();
