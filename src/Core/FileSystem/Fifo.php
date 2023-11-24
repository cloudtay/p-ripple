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

namespace Core\FileSystem;


/**
 * Pipeline
 */
class Fifo
{
    public const EXT = '.fifo';

    /**
     * @var mixed|false|resource
     */
    private mixed $stream;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $path;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name   = $name;
        $this->path   = PP_RUNTIME_PATH . '/fifo_' . $name . Fifo::EXT;
        $this->stream = fopen($this->path, 'r+');
    }

    /**
     * @param string $name
     * @return Fifo|false
     */
    public static function create(string $name): Fifo|false
    {
        $path = PP_RUNTIME_PATH . '/fifo_' . $name;
        if (file_exists($path . Fifo::EXT)) {
            return false;
        } elseif (posix_mkfifo($path . Fifo::EXT, 0666)) {
            return new self($name);
        } else {
            return false;
        }
    }

    /**
     * Create a pipeline
     * @param string $name
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return file_exists(PP_RUNTIME_PATH . '/fifo_' . $name . Fifo::EXT);
    }

    /**
     * NfsService the pipes
     * @param string $name
     * @return Fifo|false
     */
    public static function link(string $name): Fifo|false
    {
        $path = PP_RUNTIME_PATH . '/fifo_' . $name;
        if (!!file_exists($path . Fifo::EXT)) {
            return new self($name);
        } else {
            return false;
        }
    }

    /**
     * Write data to the pipeline
     * @param string $context
     * @return int
     */
    public function write(string $context): int
    {
        return fwrite($this->stream, $context);
    }

    /**
     * Read a row
     * @return string
     */
    public function getLine(): string
    {
        return fgets($this->stream);
    }

    /**
     * Reads the contents of the specified length
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        return fread($this->stream, $length);
    }

    /**
     * Get the entire pipeline content
     * @return string
     */
    public function full(): string
    {
        return stream_get_contents($this->stream);
    }

    /**
     * Destroy the pipe
     * @return void
     */
    public function release(): void
    {
        $this->close();
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Close the pipe
     * @return void
     */
    public function close(): void
    {
        if (get_resource_type($this->stream) !== 'Unknown') {
            fclose($this->stream);
        }
    }

    /**
     * Set the jam mode
     * @param bool $bool
     * @return bool
     */
    public function setBlocking(bool $bool): bool
    {
        return stream_set_blocking($this->stream, $bool);
    }

    /**
     * Gets the current pipeline name
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

}
