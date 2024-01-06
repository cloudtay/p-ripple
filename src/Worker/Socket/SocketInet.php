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
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        socket_set_nonblock($socket);
        foreach ($options as $option => $value) {
            socket_set_option($socket, SOL_SOCKET, $option, $value);
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
     * @return resource
     * @throws Exception
     */
    public static function createStream(string $address, int $port, array|null $options = [])
    {
        $stream = stream_socket_server("tcp://{$address}:{$port}", $errno, $errorMessages, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$stream) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        $socket = socket_import_stream($stream);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        socket_set_nonblock($socket);
        foreach ($options as $option => $value) {
            socket_set_option($socket, SOL_SOCKET, $option, $value);
        }
        return $stream;
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
        socket_set_nonblock($socket);
        foreach ($options as $option => $value) {
            socket_set_option($socket, SOL_SOCKET, $option, $value);
        }
        if (!socket_connect($socket, $address, $port)) {
            return false;
        }
        return $socket;

    }

    /**
     * @param string     $address
     * @param int        $port
     * @param array|null $options
     * @return resource
     * @throws Exception
     */
    public static function connectStream(string $address, int $port, array|null $options = [])
    {
        $stream = stream_socket_client("tcp://{$address}:{$port}");
        if (!$stream) {
            throw new Exception('Unable to create Unix socket, probably process is occupied');
        }
        $socket = socket_import_stream($stream);
        socket_set_nonblock($socket);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        foreach ($options as $option => $value) {
            socket_set_option($socket, SOL_SOCKET, $option, $value);
        }
        return $stream;
    }
}
