<?php declare(strict_types=1);
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

namespace Cclilshy\PRipple\Core\Net;

use Cclilshy\PRipple\Filesystem\Exception\FileException;
use Cclilshy\PRipple\Utils\IO;
use Exception;
use Socket as SocketNative;
use function array_shift;
use function file_exists;
use function fopen;
use function fwrite;
use function min;
use function posix_getpid;
use function socket_get_option;
use function socket_getsockname;
use function socket_set_block;
use function socket_set_nonblock;
use function socket_set_option;
use function spl_object_hash;
use function str_split;
use function strlen;
use function substr;
use function unlink;
use const PP_MAX_FILE_HANDLE;
use const PP_RUNTIME_PATH;
use const SO_SNDBUF;
use const SOL_SOCKET;

/**
 * @class Socket 套接字类型通道
 */
class Socket extends Stream
{
    public const string TYPE_UNIX = 'TYPE_UNIX';
    public const string TYPE_INET = 'TYPE_INET';

    /**
     * 用户连接时
     * @var string
     */
    protected readonly string $hash;

    /**
     * 套接字实体
     * @var mixed
     */
    protected readonly int $createTime;

    /**
     * 套接字类型
     * @var string $type
     */
    public string $type;

    /**
     * 已经弃用的连接,无需多余处理
     * 所有资源已被抛弃并释放对应文件,但该对象依然在某地方的内存空间被保留
     * @var bool
     */
    public bool $deprecated = false;

    /**
     * 用户地址
     * @var bool
     */
    public bool $openBuffer = false;

    /**
     * 在管理器中的键名
     * @var string
     */
    public string $address;

    /**
     *
     * @var SocketNative
     */
    public SocketNative $socket;

    /**
     * 套接字远端端口
     *
     * @var int
     */
    protected readonly int $port;

    /**
     * 发送缓冲区大小
     * @var int
     */
    protected int $sendBufferSize;

    /**
     * 接收缓冲区大小
     * @var int
     */
    protected int $receiveBufferSize;

    /**
     * 发送低水位大小
     * @var int
     */
    protected int $sendLowWaterSize;

    /**
     * 接收低水位大小
     * @var int
     */
    protected int $receiveLowWaterSize;

    /**
     * 文件缓冲区
     * @var string
     */
    protected string $sendingBuffer = '';

    /**
     * 文件缓冲区文件
     * @var Stream
     */
    protected Stream $bufferFile;

    /**
     * 文件缓冲区文件路径
     * @var string
     */
    protected string $bufferFilePath;

    /**
     * 文件缓冲长度
     * @var int
     */
    protected int $bufferLength = 0;

    /**
     * 缓冲指针位置
     * @var int
     */
    protected int $bufferPoint = 0;

    /**
     * 自定义的名称
     * @var string|int
     */
    protected string|int $name = '';

    /**
     * 自定义身份标识
     * @var string|int
     */
    protected string|int $identity;

    /**
     * @param Stream $stream
     */
    public function __construct(mixed $stream)
    {
        parent::__construct($stream);
        socket_getsockname($this->socket, $address, $port);
        $this->type           = match ($this->meta['stream_type']) {
            'unix_socket'                  => Socket::TYPE_UNIX,
            'tcp_socket', 'tcp_socket/ssl' => Socket::TYPE_INET,
        };
        $this->address        = $address;
        $this->port           = $port ?? 0;
        $this->hash           = spl_object_hash($this->socket);
        $this->bufferFilePath = PP_RUNTIME_PATH . "/socket_buffer_" . posix_getpid() . "_{$this->hash}.socket";
        if (file_exists($this->bufferFilePath)) {
            unlink($this->bufferFilePath);
        }
    }

    /**
     * 获取在管理器中的键名
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * 获取客户端连接时间
     * @return int
     */
    public function getCreateTime(): int
    {
        return $this->createTime;
    }

    /**
     * 获取socket实体
     * @return SocketNative
     */
    public function getSocket(): SocketNative
    {
        return $this->socket;
    }

    /**
     * 获取客户端地址
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * 获取客户端端口
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * 获取客户端名称
     * @return string|int
     */
    public function getName(): string|int
    {
        return $this->name;
    }

    /**
     * 设置客户端名称
     * @param string|int $name
     * @return void
     */
    public function setName(string|int $name): void
    {
        $this->name = $name;
    }

    /**
     * 获取发送缓冲区大小
     * @return int
     */
    public function getSendBufferSize(): int
    {
        return $this->sendBufferSize = socket_get_option($this->socket, SOL_SOCKET, SO_SNDBUF);
    }

    /**
     * 设置发送缓冲区大小
     * @param int $size
     * @return bool
     */
    public function setSendBufferSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, $size)) {
            $this->sendBufferSize = $size;
            return true;
        }
        return false;
    }


    /**
     * 获取发送低水位大小
     * @return int
     */
    public function getSendLowWaterSize(): int
    {
        return $this->sendLowWaterSize = socket_get_option($this->socket, SOL_SOCKET, SO_SNDLOWAT);
    }

    /**
     * 设置发送低水位大小
     * @param int $size
     * @return bool
     */
    public function setSendLowWaterSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_SNDLOWAT, $size)) {
            $this->sendLowWaterSize = $size;
            return true;
        }
        return false;
    }

    /**
     * 获取接收低水位大小
     * @return int
     */
    public function getReceiveLowWaterSize(): int
    {
        return $this->receiveLowWaterSize = socket_get_option($this->socket, SOL_SOCKET, SO_RCVLOWAT);
    }

    /**
     * 设置接收低水位大小
     * @param int $size
     * @return bool
     */
    public function setReceiveLowWaterSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVLOWAT, $size)) {
            $this->receiveLowWaterSize = $size;
            return true;
        }
        return false;
    }

    /**
     * 获取接收缓冲区大小
     * @return int
     */
    public function getReceiveBufferSize(): int
    {
        return $this->receiveBufferSize = socket_get_option($this->socket, SOL_SOCKET, SO_RCVBUF);
    }

    /**
     * 设置接收缓冲区大小
     * @param int $size
     * @return bool
     */
    public function setReceiveBufferSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, $size)) {
            $this->receiveBufferSize = $size;
            return true;
        }
        return false;
    }

    /**
     * 设置为堵塞模式
     * @return bool
     */
    public function setBlock(): bool
    {
        return socket_set_block($this->stream);
    }

    /**
     * 设置为非堵塞模式
     * @return bool
     */
    public function setNoBlock(): bool
    {
        return socket_set_nonblock($this->stream);
    }

    /**
     * 获取文件缓冲区长度
     * @return int
     */
    public function getBufferLength(): int
    {
        return $this->bufferLength;
    }

    /**
     * @return void
     * @throws FileException
     */
    private function openBuffer(): void
    {
        if (count(get_resources()) < PP_MAX_FILE_HANDLE) {
            if (touch($this->bufferFilePath)) {
                $this->bufferFile   = new Stream(fopen($this->bufferFilePath, 'r+'));
                $this->openBuffer   = true;
                $this->bufferPoint  = 0;
                $this->bufferLength = 0;
                IO::cleanBuffer($this);
            } else {
                throw new FileException('Unable to create socket buffer buffer file, please check directory permissions: ' . $this->bufferFilePath);
            }
        } else {
            fwrite(STDIN, "Error, the maximum number of open handles has been reached: {$this->bufferFilePath}\n");
        }
    }

    /**
     * @return void
     */
    private function closeBuffer(): void
    {
        $this->bufferFile->close();
        $this->openBuffer   = false;
        $this->bufferPoint  = 0;
        $this->bufferLength = 0;
        unlink($this->bufferFile->path);
    }

    /**
     * 写缓冲到文件缓冲区
     * 该方法会将文件指针移动到末尾
     * @param string $context
     * @return void
     * @throws FileException
     */
    private function bufferToFile(string $context): void
    {
        if ($this->openBuffer === false) {
            $this->openBuffer();
        }
        $this->bufferFile->seek(0, SEEK_END);
        $this->bufferLength += $this->bufferFile->write($context);
    }


    /**
     * 实时写入数据
     * 写入数据失败时将不再抛出异常而是返回false
     * @param string $string
     * @return int
     * @throws FileException
     * @throws \Cclilshy\PRipple\Core\Net\Exception
     */
    public function write(string $string): int
    {
        try {
            $transferComplete = 0;
            if ($this->openBuffer) {
                $this->bufferFile->seek($this->bufferPoint);
                while ($this->bufferLength > 0) {
                    $readLength                  = min($this->sendBufferSize, $this->bufferLength);
                    $bufferContextFragment       = $this->bufferFile->read($readLength);
                    $bufferContextFragmentLength = strlen($bufferContextFragment);
                    $writeLength                 = parent::write($bufferContextFragment);
                    if ($writeLength === 0) {
                        break;
                    } elseif ($writeLength !== $bufferContextFragmentLength) {
                        $transferComplete   += $writeLength;
                        $this->bufferPoint  += $writeLength;
                        $this->bufferLength -= $writeLength;
                    } else {
                        $transferComplete   += $writeLength;
                        $this->bufferPoint  += $writeLength;
                        $this->bufferLength -= $writeLength;
                    }
                }
            }
            $list = str_split($string, $this->getSendBufferSize());
            while ($item = array_shift($list)) {
                if ($this->openBuffer) {
                    $this->bufferToFile($item);
                    continue;
                }
                $writeLength = parent::write($item);
                if ($writeLength === 0) {
                    $full = false;
                } elseif ($writeLength !== strlen($item)) {
                    $full             = false;
                    $item             = substr($item, $writeLength);
                    $transferComplete += $writeLength;
                } else {
                    $full = true;
                }
                if (!$full) {
                    $this->bufferToFile($item);
                    while ($item = array_shift($list)) {
                        $this->bufferToFile($item);
                    }
                    return $transferComplete;
                }
                $transferComplete += $writeLength;
            }
            if ($this->openBuffer && $this->bufferLength === 0) {
                $this->closeBuffer();
            }
            return $transferComplete;
        } catch (FileException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new \Cclilshy\PRipple\Core\Net\Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * 实时读取数据
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        if ($length === 0) {
            $length = $this->getReceiveBufferSize();
        }
        return parent::read($length);
    }

    /**
     * 接受套接字连接
     * @return resource|false
     */
    public function accept(): mixed
    {
        return stream_socket_accept($this->stream);
    }

    /**
     * 关闭套接字连接
     * @return void
     */
    public function destroy(): void
    {
        if ($this->openBuffer) {
            $this->closeBuffer();
        }
    }

    /**
     * Resolve the address
     * @param string $address
     * @return array
     * @throws Exception
     */
    public static function parseAddress(string $address): array
    {
        $type        = match (true) {
            str_contains($address, 'unix://') => Socket::TYPE_UNIX,
            str_contains($address, 'tcp://')  => Socket::TYPE_INET,
            default                           => throw new Exception('Invalid address')
        };
        $full        = strtolower(str_replace(['unix://', 'tcp://'], '', $address));
        $addressInfo = explode(':', $full);
        $host        = $addressInfo[0];
        $port        = intval(($addressInfo[1] ?? 0));
        return [$type, $host, $port];
    }
}
