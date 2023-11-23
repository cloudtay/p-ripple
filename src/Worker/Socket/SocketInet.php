<?php
declare(strict_types=1);

namespace Worker\Socket;

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
    public static function create(string $address, int $port, array|null $options = []): Socket
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new Exception('Unable to create INET socket, please close the running process');
        }
        foreach ($options as $option => $value) {
            if ($option === 'nonblock') {
                socket_set_nonblock($socket);
            } else {
                socket_set_option($socket, SOL_SOCKET, $option, $value);
            }
        }
        if (!socket_bind($socket, $address, $port)) {
            throw new Exception("Unable to bind socket address > {$address} : {$port}");
        }
        socket_listen($socket);
        return $socket;
    }

    /**
     * @param string     $address
     * @param int        $port
     * @param array|null $options
     * @return Socket|false
     */
    public static function connect(string $address, int $port, array|null $options = []): Socket|false
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        foreach ($options as $option => $value) {
            if ($option === 'nonblock') {
                socket_set_nonblock($socket);
            } else {
                socket_set_option($socket, SOL_SOCKET, $option, $value);
            }
        }
        if (!socket_connect($socket, $address, $port)) {
            return false;
        }
        return $socket;

    }
}
