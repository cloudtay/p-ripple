<?php
include __DIR__ . '/vendor/autoload.php';

use PRipple\PRipple;
use function Cclilshy\PRipple\async;
use function Cclilshy\PRipple\delay;
use function Cclilshy\PRipple\fork;
use function Cclilshy\PRipple\loop;

$master = PRipple::instance()->initialize();

async(function () {
    delay(3); #延时3秒执行
    echo 'hello,world' . PHP_EOL;
});

async(function () {
    delay(3); #延时3秒执行
    echo 'hello,world' . PHP_EOL;
});

async(function () {
    fork(function () {
        fork(function () {
            $someProcessId = fork(function () {
                echo 'child process' . PHP_EOL;
            });
            echo "someProcessId: {$someProcessId} " . PHP_EOL;
        });
    });
});

# 如果你知道的话,你可以在任何地方向任何进程发送信号
// signal($someProcessId, SIGTERM);

# 创建一个异步循环
loop(1, function () {
    echo 'loop' . PHP_EOL;
});

$master->launch();
