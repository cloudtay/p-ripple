<?php
declare(strict_types=1);

namespace Worker\Tunnel;

use Core\Constants;
use Core\FileSystem\File;
use Core\FileSystem\FileException;
use Core\Map\EventMap;
use Core\Std\TunnelStd;
use Exception;
use Worker\Prop\Build;
use Worker\Worker;

/**
 * 套接字类型通道
 */
class SocketTunnel implements TunnelStd
{
    /**
     * 文件缓冲区扩展名
     * @var string
     */
    public const EXT = '.tunnel';

    /**
     * 弃用的连接
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
    protected readonly string $address;


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
     * 套接字远端端口
     * @var mixed
     */
    protected readonly mixed $socket;


    /**
     * 发送缓冲区大小
     * @var int
     */
    protected readonly int $port;


    /**
     * 接收缓冲区大小
     * @var int
     */
    protected int $sendBufferSize;


    /**
     * 发送低水位大小
     * @var int
     */
    protected int $receiveBufferSize;


    /**
     * 接收低水位大小
     * @var int
     */
    protected int $sendLowWaterSize;


    /**
     * 总共接收流量
     * @var int
     */
    protected int $receiveLowWaterSize;


    /**
     * 总共发送流量
     * @var int
     */
    protected int $sendFlowCount = 0;


    /**
     * 发送丢包储存区
     * @var int
     */
    protected int $receiveFlowCount = 0;


    /**
     * 文件缓冲区
     * @var string
     */
    protected string $sendingBuffer = '';


    /**
     * 文件缓冲区文件
     * @var FileTunnel
     */
    protected FileTunnel $bufferFile;


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
    protected string|int $name;


    /**
     * 自定义身份标识
     * @var string|int
     */
    protected string|int $identity;


    /**
     * @param mixed $socket
     */
    public function __construct(mixed $socket)
    {
        socket_getsockname($socket, $address, $port);
        $this->address = $address;
        $this->port = $port ?? 0;
        $this->hash = Worker::getNameBySocket($socket);
        $this->socket = $socket;
        $this->name = '';
        $this->sendBufferSize = socket_get_option($socket, SOL_SOCKET, SO_SNDBUF);
        $this->receiveBufferSize = socket_get_option($socket, SOL_SOCKET, SO_RCVBUF);
        $this->sendLowWaterSize = socket_get_option($socket, SOL_SOCKET, SO_SNDLOWAT);
        $this->receiveLowWaterSize = socket_get_option($socket, SOL_SOCKET, SO_RCVLOWAT);
        $this->bufferFilePath = PP_RUNTIME_PATH . '/socket_buffer_' . getmypid() . '_' . $this->hash . SocketTunnel::EXT;
        if (File::exists($this->bufferFilePath)) {
            unlink($this->bufferFilePath);
        }
    }

    /**
     * 不可以直接连接
     * @param string $name
     * @return false|static
     */
    public static function link(string $name): self|false
    {
        return false;
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
     * @return mixed
     */
    public function getSocket(): mixed
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
     * 获取客户端身份标识
     * @return string|int
     */
    public function getIdentity(): string|int
    {
        return $this->identity ?? '';
    }

    /**
     * 设置客户端身份标识
     * @param string|int $identity
     * @return void
     */
    public function setIdentity(string|int $identity): void
    {
        $this->identity = $identity;
    }

    /**
     * 获取发送总流量
     * @return int
     */
    public function getSendFlowCount(): int
    {
        return $this->sendFlowCount;
    }

    /**
     * 获取接收缓冲区大小
     * @return int
     */
    public function getReceiveFlowCount(): int
    {
        return $this->receiveFlowCount;
    }

    /**
     * 获取发送缓冲区大小
     * @return int
     */
    public function getSendBufferSize(): int
    {
        return $this->sendBufferSize;
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
        return $this->sendLowWaterSize;
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
        return $this->receiveLowWaterSize;
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
        return $this->receiveBufferSize;
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
        $this->bufferFile->adjustPoint(0, SEEK_END);
        $this->bufferFile->write($context);
        $this->bufferLength += strlen($context);
    }

    /**
     * @return void
     * @throws FileException
     */
    private function openBuffer(): void
    {
        if (count(get_resources()) < PP_MAX_FILE_HANDLE) {
            if ($bufferFile = File::create($this->bufferFilePath, 'r+')) {
                $this->bufferFile = FileTunnel::create($bufferFile);
                $this->openBuffer = true;
                $this->bufferPoint = 0;
                $this->bufferLength = 0;
                EventMap::push(Build::new(Constants::EVENT_SOCKET_BUFFER, $this, SocketTunnel::class));
            } else {
                throw new FileException("Unable to create socket buffer buffer file, please check directory permissions: " . $this->bufferFilePath);
            }
        } else {
            echo "Error, the maximum number of open handles has been reached: " . $this->bufferFilePath . PHP_EOL;
        }
    }

    /**
     * 创建连接
     * @param mixed $base
     * @return false|static
     * @throws Exception
     */
    public static function create(mixed $base): self|false
    {
        return new self($base);
    }

    /**
     * 实时写入数据
     * 写入数据失败时将不再抛出异常而是返回false
     * @param string $context
     * @param bool|null $async
     * @return int|false
     * @throws FileException
     */
    public function write(string $context, bool|null $async = true): int|false
    {
        try {
            $transferComplete = 0;
            # 处理文件缓冲数据
            if ($this->openBuffer) {
                while ($this->bufferLength > 0) {
                    $this->bufferFile->adjustPoint($this->bufferPoint);
                    $readLength = min($this->sendBufferSize, $this->bufferLength);
                    $bufferContextFragment = $this->bufferFile->read($readLength, $resultLength);
                    $bufferContextFragmentLength = strlen($bufferContextFragment);
                    $writeLength = socket_send($this->socket, $bufferContextFragment, $bufferContextFragmentLength, 0);
                    if ($writeLength === false) {
                        break;
                    } elseif ($writeLength !== $bufferContextFragmentLength) {
                        $transferComplete += $writeLength;
                        $this->bufferPoint += $writeLength;
                        $this->bufferLength -= $writeLength;
                    } else {
                        $this->bufferPoint += $writeLength;
                        $this->bufferLength -= $writeLength;
                        $transferComplete += $writeLength;
                    }
                }
            }

            // 处理请求文本
            $list = str_split($context, $this->sendBufferSize);
            while ($item = array_shift($list)) {
                if ($this->openBuffer) {
                    $this->bufferToFile($item);
                    continue;
                }
                $writeLength = socket_send($this->socket, $item, strlen($item), 0);
                if ($writeLength === false) {
                    $full = false;
                } elseif ($writeLength !== strlen($item)) {
                    $full = false;
                    $item = substr($item, $writeLength);
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
            return false;
        }
    }


    /**
     * 实时读取数据
     * @param int $length
     * @param int|null &$resultLength
     * @return string|false
     */
    public function read(int $length, int|null &$resultLength): string|false
    {
        if ($length === 0) {
            $length = $this->receiveBufferSize;
            $target = false;
        } else {
            // 严格接收模式
            $target = true;
        }
        $data = '';
        $resultLength = 0;
        if (!$recLength = socket_recv($this->socket, $_buffer, min($length, $this->receiveBufferSize), 0)) {
            return false;
        }
        $length -= $recLength;
        $data .= $_buffer;
        $resultLength += $recLength;
        $this->receiveFlowCount += $recLength;
        while ($target && $length > 0) {
            if (!$recLength = socket_recv($this->socket, $_buffer, min($length, $this->receiveBufferSize), 0)) {
                return false;
            }
            $length -= $recLength;
            $data .= $_buffer;
            $resultLength += $recLength;
            $this->receiveFlowCount += $recLength;
        }
        return $data;
    }

    /**
     * @return void
     */
    private function closeBuffer(): void
    {
        $this->bufferFile->destroy();
        $this->openBuffer = false;
        $this->bufferPoint = 0;
        $this->bufferLength = 0;
        EventMap::push(Build::new(Constants::EVENT_SOCKET_BUFFER_UN, $this, SocketTunnel::class));
    }

    /**
     * 关闭套接字连接
     * @return void
     */
    public function destroy(): void
    {
        $this->close();
        if ($this->openBuffer) {
            $this->closeBuffer();
        }
    }

    /**
     * @return void
     */
    public function close(): void
    {
        socket_close($this->socket);
    }

    /**
     * 设置为堵塞模式
     * @return bool
     */
    public function setBlock(): bool
    {
        return socket_set_block($this->socket);
    }

    /**
     * 设置为非堵塞模式
     * @return bool
     */
    public function setNoBlock(): bool
    {
        return socket_set_nonblock($this->socket);
    }

    /**
     * 堵塞推送数据
     * @return int|false
     * @throws Exception
     */
    public function truncate(): int|false
    {
        $transferComplete = 0;
        // 处理缓冲区数据
        $transferComplete += $this->write($this->sendingBuffer);

        // 处理缓冲文件数据
        if ($this->openBuffer && $this->bufferLength > 0) {
            $this->bufferFile->adjustPoint($this->bufferPoint);
            while ($this->bufferLength > 0 && $bufferContextFragment = $this->bufferFile->read(min($this->sendBufferSize, $this->bufferLength), $resultLength)) {
                $handledLength = $this->write($bufferContextFragment);
                $this->bufferLength -= $handledLength;
                $transferComplete += $handledLength;
            }
        }

        return $transferComplete;
    }

    /**
     * 通过协议发送数据
     * @param string $protocol
     * @param string $method
     * @param array  $options
     * @return mixed
     */
    public function sendByAgree(string $protocol, string $method, array $options): mixed
    {
        return call_user_func_array([$protocol, $method], array_merge([$this], $options));
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
     * @return string[]
     */
    public function __sleep()
    {
        return [
            'address',
            'hash',
            'createTime',
            'port',
            'sendBufferSize',
            'receiveBufferSize',
            'sendLowWaterSize',
            'receiveLowWaterSize',
            'sendFlowCount',
            'receiveFlowCount',
            'sendingBuffer',
            'bufferLength',
            'bufferPoint',
            'name',
            'identity',
            'bufferFilePath',
            'activeTime'
        ];
    }
}