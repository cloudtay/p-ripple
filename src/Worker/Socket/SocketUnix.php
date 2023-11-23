<?php
declare(strict_types=1);

namespace Worker\Socket;

use Exception;
use Socket;

/**
 * UNIX套接字
 */
class SocketUnix
{
    /**
     * Create a UNIX socket with a custom buffer size
     * @param string     $socketFile SOCKET FILE ADDRESS
     * @param array|null $options
     * @return Socket
     * @throws Exception
     */
    public static function create(string $socketFile, array|null $options = []): Socket
    {
        if (file_exists($socketFile)) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$socket) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        foreach ($options as $option => $value) {
            if ($option === 'nonblock') {
                socket_set_nonblock($socket);
            } else {
                socket_set_option($socket, SOL_SOCKET, $option, $value);
            }
        }
        if (!socket_bind($socket, $socketFile)) {
            throw new Exception('Unable to bind socket, please check directory permissions ' . $socketFile);
        }
        socket_listen($socket);
        return $socket;
    }

    /**
     * @param string     $socketFile
     * @param int|null   $bufferSize
     * @param array|null $options
     * @return Socket|false
     * @throws Exception
     */
    public static function connect(string $socketFile, int|null $bufferSize = 1024 * 1024, array|null $options = []): Socket|false
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, $bufferSize);
        socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, $bufferSize);
        foreach ($options as $option => $value) {
            if ($option === 'nonblock') {
                socket_set_nonblock($socket);
            } else {
                socket_set_option($socket, SOL_SOCKET, $option, $value);
            }
        }
        $_ = socket_connect($socket, $socketFile);
        if ($_) {
            return $socket;
        } else {
            throw new Exception("Unable to connect Unix socket, {$socketFile}");
        }
    }
}
