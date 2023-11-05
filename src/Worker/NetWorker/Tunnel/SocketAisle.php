<?php
declare(strict_types=1);

namespace PRipple\Worker\NetWorker\Tunnel;

use Exception;
use PRipple\FileSystem\File;
use PRipple\FileSystem\FileException;
use PRipple\PRipple;
use PRipple\Std\TunnelStd;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker;

/**
 * 套接字类型通道
 */
class SocketAisle implements TunnelStd
{
    public const EXT = '.tunnel';
    // 用户地址
    public bool $openCache = false;
    // 在管理器中的键名
    protected readonly string $address;
    // 用户连接时
    protected readonly string $hash;
    // 套接字实体
    protected readonly int $createTime;
    // 套接字远端端口
    protected readonly mixed $socket;
    // 发送缓冲区大小
    protected readonly int $port;
    // 接收缓冲区大小
    protected int $sendBufferSize;
    // 发送低水位大小
    protected int $receiveBufferSize;
    // 接收低水位大小
    protected int $sendLowWaterSize;
    // 总共接收流量
    protected int $receiveLowWaterSize;
    // 总共发送流量
    protected int $sendFlowCount = 0;
    // 发送丢包储存区
    protected int $receiveFlowCount = 0;
    // 文件缓冲区
    protected string $sendBuffer = '';
    // 文件缓冲区文件路径
    protected FileAisle $cacheFile;
    // 文件缓冲长度
    protected string $cacheFilePath;
    // 缓存指针位置
    protected int $cacheLength = 0;
    // 自定义的名称
    protected int $cachePoint = 0;
    // 自定义身份标识
    protected string|int $name;
    // 上次活跃时间
    protected string|int $identity;

    /**
     * @param mixed $socket
     */
    public function __construct(mixed $socket)
    {
        socket_getsockname($socket, $address, $port);
        $this->address = $address;
        $this->port = $port ?? 0;
        $this->hash = NetWorker::getNameBySocket($socket);
        $this->socket = $socket;
        $this->name = '';
        $this->sendBufferSize = socket_get_option($socket, SOL_SOCKET, SO_SNDBUF);
        $this->receiveBufferSize = socket_get_option($socket, SOL_SOCKET, SO_RCVBUF);
        $this->sendLowWaterSize = socket_get_option($socket, SOL_SOCKET, SO_SNDLOWAT);
        $this->receiveLowWaterSize = socket_get_option($socket, SOL_SOCKET, SO_RCVLOWAT);
        $this->cacheFilePath = PP_RUNTIME_PATH . '/socket_cache_' . getmypid() . '_' . $this->hash . SocketAisle::EXT;
        if (File::exists($this->cacheFilePath)) {
            unlink($this->cacheFilePath);
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
     * @param string $context
     * @return void
     * @throws FileException
     */
    public function cacheToFile(string $context): void
    {
        if ($this->openCache === false) {
            $this->openCache();
        }
        $this->cacheFile->adjustPoint(0, SEEK_END);
        $this->cacheFile->write($context);
        $this->cacheLength += strlen($context);
    }

    /**
     * @return void
     * @throws FileException
     */
    private function openCache(): void
    {
        if (count(get_resources()) < PP_MAX_FILE_HANDLE) {
            if ($cacheFile = File::create($this->cacheFilePath, 'r+')) {
                $this->cacheFile = FileAisle::create($cacheFile);
                $this->openCache = true;
                PRipple::publishAsync(Build::new('socket.buffer', $this, SocketAisle::class));

            } else {
                throw new FileException("Unable to create socket cache buffer file, please check directory permissions: " . $this->cacheFilePath);
            }
        } else {
            echo "Error, the maximum number of open handles has been reached: " . $this->cacheFilePath . PHP_EOL;
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
     * @param string $context
     * @param bool $async
     * @return int|false
     */
    public function write(string $context, bool $async = true): int|false
    {
        try {
            //        if (!$async) {
            //            $handledLength = 0;
            //            $tasks = str_split($context, $this->sendBufferSize);
            //            do {
            //                if ($task = array_shift($tasks)) {
            //                    $this->sendBuffer .= $task;
            //                }
            //                $writeList = [$this->socket];
            //                socket_select($_, $writeList, $_, null, 1000);
            //                $_buffer = substr($this->sendBuffer, 0, $this->sendBufferSize);
            //                $writeLength = socket_send($this->socket, $_buffer, strlen($_buffer), 0);
            //                if ($writeLength === false || $writeLength === 0) {
            //                    return false;
            //                }
            //                $handledLength += $writeLength;
            //                $this->sendBuffer = substr($_buffer, $writeLength);
            //                $this->sendFlowCount += $writeLength;
            //            } while (!empty($buffer) || count($tasks) > 0);
            //            return $handledLength;
            //        }
            $handledLengthCount = 0;

            // 处理缓冲区数据
            $list = str_split($this->sendBuffer, $this->sendBufferSize);
            while ($item = array_shift($list)) {
                if (!$writeLength = socket_send($this->socket, $item, strlen($item), 0)) {
                    $this->cacheToFile($item);
                    $handledLengthCount += strlen($item);
                    while ($item = array_shift($list)) {
                        $this->cacheToFile($item);
                        $handledLengthCount += strlen($item);
                    }
                    return $handledLengthCount;
                }
                $this->sendBuffer = substr($this->sendBuffer, $writeLength);
                $handledLengthCount += $writeLength;
            }

            // 处理缓存文件数据
            if ($this->openCache && $this->cacheLength > 0) {
                $this->cacheFile->adjustPoint($this->cachePoint);
                while ($this->cacheLength > 0 && $cacheContextFragment = $this->cacheFile->read(min($this->sendBufferSize, $this->cacheLength), $resultLength)) {
                    if (!$handledLength = socket_send($this->socket, $cacheContextFragment, strlen($cacheContextFragment), 0)) {
                        $this->cacheToFile($context);
                        return $handledLengthCount;
                    }
                    $this->cachePoint += $handledLength;
                    $this->cacheLength -= $handledLength;
                    $handledLengthCount += $handledLength;
                }
            }

        } catch (FileException $exception) {
//            PRipple::printExpect($exception);
            return false;
        }

        try {
            // 处理请求文本
            $list = str_split($context, $this->sendBufferSize);
            while ($item = array_shift($list)) {
                if (!$handledLength = socket_send($this->socket, $item, strlen($item), 0)) {
                    $this->cacheToFile($item);
                    while ($item = array_shift($list)) {
                        $this->cacheToFile($item);
                    }
                    return $handledLengthCount;
                }
                $handledLengthCount += $handledLength;
            }

            if ($this->openCache) {
                $this->closeCache();
            }
            return $handledLengthCount;
        } catch (Exception $exception) {
//            PRipple::printExpect($exception);
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
            $_rs = [$this->socket];
            $_es = $_rs;

            if (!socket_select($_rs, $_, $_es, 0, 1000)) {
                return false;
            }
            if (!empty($_es)) {
                return false;
            }
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
    private function closeCache(): void
    {
        $this->cacheFile->destroy();
        $this->openCache = false;
        PRipple::publishAsync(Build::new('socket.unBuffer', $this, SocketAisle::class));

    }

    /**
     * 关闭套接字连接
     * @return void
     */
    public function destroy(): void
    {
        socket_close($this->socket);
        if ($this->openCache) {
            $this->closeCache();
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
        $handledLengthCount = 0;
        // 处理缓冲区数据
        $handledLengthCount += $this->write($this->sendBuffer);

        // 处理缓存文件数据
        if ($this->openCache && $this->cacheLength > 0) {
            $this->cacheFile->adjustPoint($this->cachePoint);
            while ($this->cacheLength > 0 && $cacheContextFragment = $this->cacheFile->read(min($this->sendBufferSize, $this->cacheLength), $resultLength)) {
                $handledLength = $this->write($cacheContextFragment);
                $this->cacheLength -= $handledLength;
                $handledLengthCount += $handledLength;
            }
        }

        return $handledLengthCount;
    }

    /**
     * 通过协议发送数据
     * @param string $protocol
     * @param string $method
     * @param array $options
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
    public function getCacheLength(): int
    {
        return $this->cacheLength;
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
            'sendBuffer',
            'cacheLength',
            'cachePoint',
            'name',
            'identity',
            'cacheFilePath',
            'activeTime'
        ];
    }
}
