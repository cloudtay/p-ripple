<?php

use Worker\Socket\SocketUnix;

include __DIR__ . '/../vendor/autoload.php';


if (file_exists('/tmp/pripple.sock')) {
    unlink('/tmp/pripple.sock');
}
try {
    $socket = SocketUnix::createStream('/tmp/pripple.sock');
} catch (Exception $e) {
}
die;
