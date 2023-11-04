<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Tunnel;

use Cclilshy\PRipple\FileSystem\Fifo;
use Cclilshy\PRipple\Std\TunnelStd;

class FifoAisle implements TunnelStd
{
    public const EXT = '.fifo';

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
     * @param bool   $async
     * @return int|bool
     */
    public function write(string $context, bool $async = false): int|bool
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
