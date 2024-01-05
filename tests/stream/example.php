<?php
include __DIR__ . '/../vendor/autoload.php';


if (file_exists('/tmp/pripple.sock')) {
    unlink('/tmp/pripple.sock');
}
$socket = \Worker\Socket\SocketUnix::createStream('/tmp/pripple.sock');
die;
