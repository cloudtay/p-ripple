<?php

use Worker\Socket\SocketUnix;

include __DIR__ . '/../vendor/autoload.php';

$s = SocketUnix::connect('/tmp/test.sock');
socket_set_nonblock($s);
socket_write($s, "test\n");

$processId = pcntl_fork();
if ($processId === 0) {
    sleep(1);
    socket_write($s, "is child\n");
    sleep(1);
    socket_close($s);
    exit;
}

sleep(5);
socket_write($s, "is parent\n");
