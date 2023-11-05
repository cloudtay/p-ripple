<?php
declare(strict_types=1);

namespace PRipple\Worker\NetWorker\SocketType;

use Exception;
use Socket;

/**
 * INET套接字
 */
class SocketInet
{
    /**
     * @throws Exception
     */
    public static function create(string $address, int $port, int|null $type = SOCK_STREAM, array|null $options = []): Socket
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$server) {
            throw new Exception('Unable to create INET socket, please close the running process');
        }
        foreach ($options as $item => $value) {
            socket_set_option($server, SOL_SOCKET, $item, $value);
        }
        if (!socket_bind($server, $address, $port)) {
            throw new Exception("Unable to bind socket address > {$address} : {$port}", 1);
        }
        socket_listen($server);
        return $server;
    }
}
