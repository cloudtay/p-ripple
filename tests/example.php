<?php
include __DIR__ . '/vendor/autoload.php';

use Ext\PDOProxy\PDOProxy;
use Ext\WebSocket\WebSocket;
use Tests\TestTCP;
use Tests\TestWs;

$kernel = PRipple::configure([
    'RUNTIME_PATH'     => '/tmp',
    'HTTP_UPLOAD_PATH' => '/tmp',
    'PP_RUNTIME_PATH'  => '/tmp'
]);

$ws = TestWs::new(TestWs::class)->bind('tcp://127.0.0.1:8001', [
    'nonblock'   => true,
    SO_REUSEPORT => 1
])->protocol(WebSocket::class);

$tcp = TestTCP::new(TestTCP::class)->bind('tcp://127.0.0.1:8002', [
    'nonblock'   => true,
    SO_REUSEPORT => 1
]);

$pdo = PDOProxy::new(PDOProxy::class)->connect([
    'device'   => 'mysql',
    'hostname' => '127.0.0.1',
    'database' => 'lav',
    'username' => 'root',
    'password' => '123456',
    'options'  => [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
    ]
]);

$kernel->push($ws, $tcp, $pdo)->launch();
