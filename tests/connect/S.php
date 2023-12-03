<?php

use Worker\Socket\SocketUnix;

include __DIR__ . '/../vendor/autoload.php';

file_exists('/tmp/test.sock') && unlink('/tmp/test.sock');
$s = SocketUnix::create('/tmp/test.sock');
socket_set_nonblock($s);
$cs = [];
sleep(10);
while (true) {
    $_rs   = $cs;
    $_rs[] = $s;
    $_es   = $_rs;
    if (socket_select($_rs, $_ws, $_es, 0, 1000000)) {
        foreach ($_rs as $c) {
            if ($c === $s) {
                if ($a = socket_accept($s)) {
                    $cs[] = $a;
                    socket_set_nonblock($a);
                }
            } else {
                $read = socket_read($c, 1024);
                echo spl_object_hash($c) . ':' . $read;
                if ($read === '') {
                    socket_close($c);
                    unset($cs[array_search($c, $cs)]);
                    echo 'close' . PHP_EOL;
                }
            }
        }
    } else {
        echo 'timeout' . PHP_EOL;
    }
}
