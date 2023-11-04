<?php
declare(strict_types=1);
include 'autoload.php';

try {
    $socket = socket_create($domain = AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($socket, '127.0.0.1', 3002)) {
        exit('connect failed.');
    }
    $ss = [$socket];
    $output = fopen('/tmp/test_file_output_' . getmypid(), 'w');
    while (true) {
        if (socket_select($ss, $_, $_, 1)) {
            $result = socket_recv($socket, $content, 1024, 0);
            if ($result === false || $result === 0) {
                break;
            }
            fwrite($output, $content);
        }
        $ss = [$socket];
    }
    echo 'download file md5:' . md5_file('/tmp/test_file_output_' . getmypid()) . PHP_EOL;
} catch (Exception $exception) {
    exit($e->getMessage());
}
