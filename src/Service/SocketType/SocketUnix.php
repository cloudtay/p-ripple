<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Service\SocketType;

use Exception;
use Socket;

/**
 *
 */
class SocketUnix
{
    /**
     * Create a UNIX socket with a custom buffer size
     * @param string    $sockFile   SOCKET FILE ADDRESS
     * @param bool|null $block
     * @param int|null  $bufferSize The default buffer size is 8M
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
     * @return Socket|false
     * @throws Exception
     */
    public static function connect(string $sockFile, int|null $bufferSize = 1024 * 1024): Socket|false
    {
        $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_set_option($sock, SOL_SOCKET, SO_SNDBUF, $bufferSize);
        socket_set_option($sock, SOL_SOCKET, SO_RCVBUF, $bufferSize);
        $_ = socket_connect($sock, $sockFile);
        if ($_) {
            return $sock;
        } else {
            throw new Exception("Unable to connect Unix socket, {$sockFile}");
        }
    }
}
