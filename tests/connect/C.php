<?php

use Core\Output;
use Worker\Socket\SocketUnix;

include __DIR__ . '/../vendor/autoload.php';

for ($i = 0; $i < 100; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        try {
            $s = SocketUnix::connect('/tmp/test.sock');
            sleep(3);
            socket_write($s, 'hello');
        } catch (Exception $exception) {
            Output::printException($exception);
        }
        exit(0);
    }

}

