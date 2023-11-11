<?php
declare(strict_types=1);

namespace Worker\NetWorker\Tunnel;

use FileSystem\File;
use Std\TunnelStd;

/**
 * 文件类型通道
 */
class FileAisle implements TunnelStd
{
    /**
     * @var string $ext
     */
    public const EXT = '.temp';

    /**
     * @var File|mixed $file
     */
    public File $file;

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

    // 获取连接相关信息

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
        $resultLength = $length;
        return $this->file->readWithTrace($length);
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
     * 销毁管道
     * @return void
     */
    public function destroy(): void
    {
        $this->file->destroy();
    }

    /**
     * 关闭连接
     * @return void
     */
    public function close(): void
    {
        $this->file->close();
    }

    /**
     * 移动指针
     * @param int $location
     * @param int|null $whence
     * @return int
     */
    public function adjustPoint(int $location, int|null $whence = SEEK_SET): int
    {
        return $this->file->adjustPoint($location, $whence);
    }
}
