<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

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
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
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
     * @param array|null $options
     * @return resource
     * @throws Exception
     */
    public static function createStream(string $socketFile, array|null $options = [])
    {
        $stream = stream_socket_server("unix://{$socketFile}", $errno, $errorMessages, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$stream) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        $socket = socket_import_stream($stream);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        foreach ($options as $option => $value) {
            if ($option === 'nonblock') {
                socket_set_nonblock($socket);
            } else {
                socket_set_option($socket, SOL_SOCKET, $option, $value);
            }
        }
        return $stream;
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
