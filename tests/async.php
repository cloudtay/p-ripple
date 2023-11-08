<?php
include __DIR__ . '/vendor/autoload.php';

use PRipple\PRipple;
use function Cclilshy\PRipple\async;
use function Cclilshy\PRipple\delay;

$kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$num = 0;

async(function () {
    $num = 0;

    async(function () use (&$num) {
        delay(2);
        $num = 1;
        echo '888';
    });

    echo 'lad';
    echo $num . PHP_EOL;
    delay(3);
    echo $num . PHP_EOL;
});


$kernel->launch();
