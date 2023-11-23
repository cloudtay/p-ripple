<?php

use Core\Output;
use Worker\Socket\SocketInet;

include __DIR__ . '/vendor/autoload.php';

try {
    $server = SocketInet::create('127.0.0.1', 8008, [
        SO_REUSEPORT => 1,
    ]);
} catch (Exception $e) {
    Output::info($e->getMessage());
    exit;
}

foreach (range(1, 2) as $i) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        while (true) {
            $readList = [$server];
            if (socket_select($readList, $_, $_, 0, 1000000)) {
                $client = socket_accept($server);
                socket_write($client, 'HTTP/1.1 200 OK' . "\r\n" . 'Content-Type: text/html; charset=utf-8' . "\r\n\r\n" . 'Hello World!');
                socket_close($client);
                echo 'pid: ' . posix_getpid() . PHP_EOL;
            }
        }
    }
}

sleep(1000);
