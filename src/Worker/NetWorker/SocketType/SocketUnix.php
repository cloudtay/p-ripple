<?php
declare(strict_types=1);

namespace Worker\NetWorker\SocketType;

use Exception;
use Socket;

/**
 * UNIX套接字
 */
class SocketUnix
{
    /**
     * Create a UNIX socket with a custom buffer size
     * @param string $sockFile SOCKET FILE ADDRESS
     * @param bool|null $block
     * @param int|null $bufferSize The default buffer size is 8M
     * @return Socket
     * @throws Exception
     */
    public static function create(string $sockFile, bool|null $block = true, int|null $bufferSize = 1024 * 1024): Socket
    {
        if (file_exists($sockFile)) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$sock) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        socket_set_option($sock, SOL_SOCKET, SO_SNDBUF, $bufferSize);
        socket_set_option($sock, SOL_SOCKET, SO_RCVBUF, $bufferSize);
        if (!socket_bind($sock, $sockFile)) {
            throw new Exception('Unable to bind socket, please check directory permissions ' . $sockFile);
        }
        if ($block === false) {
            socket_set_nonblock($sock);
        }
        socket_listen($sock);
        return $sock;
    }

    /**
     * @param string $sockFile
     * @param int|null $bufferSize
     * @param array|null $options
     * @return Socket|false
     * @throws Exception
     */
    public static function connect(string $sockFile, int|null $bufferSize = 1024 * 1024, array|null $options = []): Socket|false
    {
        $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_set_option($sock, SOL_SOCKET, SO_SNDBUF, $bufferSize);
        socket_set_option($sock, SOL_SOCKET, SO_RCVBUF, $bufferSize);
        foreach ($options as $option => $value) {
            if ($option === 'nonblock') {
                socket_set_nonblock($sock);
            } else {
                socket_set_option($sock, SOL_SOCKET, $option, $value);
            }
        }
        $_ = socket_connect($sock, $sockFile);
        if ($_) {
            return $sock;
        } else {
            throw new Exception("Unable to connect Unix socket, {$sockFile}");
        }
    }
}
