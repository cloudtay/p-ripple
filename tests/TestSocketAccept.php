<?php

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;

class TestSocketAccept extends TestCase
{
    public function __construct($name)
    {
        parent::__construct($name);
        file_exists('/tmp/TestSocketAccept.sock') && unlink('/tmp/TestSocketAccept.sock');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testSocketAcceptNonBlock(): void
    {
        ob_end_clean();
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_bind($socket, '/tmp/TestSocketAccept.sock');
        socket_listen($socket);
        socket_set_nonblock($socket);

        socket_accept($socket);
        echo "end\n";
        socket_close($socket);
        unlink('/tmp/TestSocketAccept.sock');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testSocketAcceptBlock(): void
    {
        ob_end_clean();
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_bind($socket, '/tmp/TestSocketAccept.sock');
        socket_listen($socket);
        socket_accept($socket);
        echo "end\n";
        socket_close($socket);
        unlink('/tmp/TestSocketAccept.sock');
    }
}
