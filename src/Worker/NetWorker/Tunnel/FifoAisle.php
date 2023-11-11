<?php
declare(strict_types=1);

namespace Worker\NetWorker\Tunnel;

use FileSystem\Fifo;
use Std\TunnelStd;

/**
 * 管道类型通道
 */
class FifoAisle implements TunnelStd
{
    /**
     *
     */
    public const EXT = '.fifo';

    /**
     * @var Fifo|mixed
     */
    private Fifo $file;

    /**
     * @param mixed $file
     */
    public function __construct(mixed $file)
    {
        $this->file = $file;
    }

    /**
     * 创建连接
     * @param mixed $base
     * @return false|static
     */
    public static function create(mixed $base): self|false
    {
        return new self($base);
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
     * 读取数据
     * @param int $length
     * @param int|null $resultLength
     * @return string
     */
    public function read(int $length, int|null &$resultLength): string
    {
        $resultLength = strlen($data = $this->file->read($length));
        return $data;
    }

    /**
     * 写入数据
     * @param string $context
     * @param bool|null $async
     * @return int|bool
     */
    public function write(string $context, bool|null $async = false): int|bool
    {
        return $this->file->write($context);
    }

    /**
     * CLOSE CONNECTION
     * @return void
     */
    public function close(): void
    {
        $this->file->close();
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        // TODO: Implement destroy() method.
        $this->file->release();
    }

    /**
     * @return bool
     */
    public function release(): bool
    {
        $this->file->release();
        return true;
    }
}
