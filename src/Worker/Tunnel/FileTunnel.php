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

namespace Worker\Tunnel;

use Core\FileSystem\File;
use Core\Std\TunnelStd;

/**
 * 文件类型通道
 */
class FileTunnel implements TunnelStd
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
