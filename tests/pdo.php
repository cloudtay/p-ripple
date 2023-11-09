<?php

use App\Facade\PDOProxy;
use App\Facade\Process;
use function PRipple\async;
use function PRipple\delay;
use function PRipple\fork;

include __DIR__ . '/vendor/autoload.php';

$kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

PDOProxy::addProxy(10, [
    'dns' => 'mysql:host=127.0.0.1;dbname=lav',
    'username' => 'root',
    'password' => '123456',
    'options' => []
]);


async(function () use (&$pid) {
    delay(10);
    echo 'test pid :' . $pid . PHP_EOL;
    Process::signal($pid, SIGTERM);
});

$pid = 0;

async(function () use (&$pid) {
    delay(1);
    $pid = fork(function () {
        $count = 0;
        while (true) {
            $count++;
            PDOProxy::query('select * from user where id = ?', [17]);
            echo "第{$count}次查询. \n";
        }
    });
});

$kernel->launch();

