<?php
declare(strict_types=1);

namespace PRipple\Tests;

use PRipple\App\Facade\PDOProxy;
use PRipple\App\Http\Http;
use PRipple\App\Http\Request;
use PRipple\App\Http\Response;
use PRipple\PRipple;
use PRipple\Protocol\WebSocket;
use function Cclilshy\PRipple\delay;

include __DIR__ . '/vendor/autoload.php';

$kernel = PRipple::instance();
$options = [SO_REUSEPORT => true];

# PDO代理池新增一个代理(详见文档:PDO代理),支持普通查询/事务查询
PDOProxy::addProxy(1, [
    'dns' => 'mysql:host=127.0.0.1;dbname=ad',
    'username' => 'root',
    'password' => '123456',
    'options' => $options
]);

# 创建一个WebSocket服务
$ws = TestWS::new('ws_worker_name')->bind('tcp://127.0.0.1:8010', $options)->protocol(WebSocket::class);

# 创建一个TCP服务
$tcp = TestTCP::new('tcp_worker_name')->bind('tcp://127.0.0.1:8011', $options);

# 创建一个HTTP服务
$http = Http::new('http_worker_name')->bind('tcp://0.0.0.0:8008', $options)->bind('tcp://127.0.0.1:8009', $options);

# 声明HTTP请求处理器
$http->defineRequestHandler(function (Request $request) use ($ws, $tcp) {
    if ($request->method === 'GET') {
        // 直接返回一个响应
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = file_get_contents(__DIR__ . '/example.html')
        );

        // 查询数据库
        $result = PDOProxy::query('select * from app_config where id = ?', [1], []);

        // 延时一秒后向所有客户端发送数据查询结果
        delay(1);
        foreach ($ws->getClients() as $client) {
            $client->send('取得数据: ' . json_encode($result));
        }

        foreach ($tcp->getClients() as $client) {
            $client->send('取得数据: ' . json_encode($result) . PHP_EOL);
        }
    } elseif ($request->upload) {
        // 在上传完成前返回一个响应
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = 'File transfer is in progress, please do not close the page...'
        );

        // 定义上传完成处理器
        $request->async(Request::EVENT_UPLOAD, function (array $info) use ($ws, $tcp) {
            foreach ($ws->getClients() as $client) {
                $client->send('file upload completed:' . json_encode($info) . PHP_EOL);
            }
            foreach ($tcp->getClients() as $client) {
                $client->send('file upload completed:' . json_encode($info) . PHP_EOL);
            }
        });

        // Http服务禁止回收该请求
        $request->await();
    } else {
        # POST请求
        yield Response::new(
            $statusCode = 200,
            $headers = ['Content-Type' => 'text/html; charset=utf-8'],
            $body = "you submitted:" . json_encode($request->post)
        );
    }
});

# 启动服务
$kernel->push($http, $ws, $tcp)->launch();
