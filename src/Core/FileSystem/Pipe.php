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

use Core\FileSystem\Std\FileStd;


/**
 * Pipeline class
 */
class Pipe implements FileStd
{
    private mixed  $resource;
    private int    $point;
    private int    $eof;
    private string $name;
    private string $path;

    /**
     * @param string $name
     * @param int    $eof
     */
    private function __construct(string $name, int $eof = -1)
    {
        $this->name     = $name;
        $this->path     = FileStd::STP . FS . $name . FileStd::EXT;
        $this->resource = fopen($this->path, 'r+');
        $this->point    = 0;
        $this->eof      = $eof;
    }

    /**
     * @param ?string $name
     * @return Pipe|false
     */
    public static function create(string|null $name = null): Pipe|false
    {
        if (!Pipe::exists($name)) {
            touch(FileStd::STP . FS . $name . FileStd::EXT);
            return new Pipe($name);
        }
        return false;
    }

    /**
     * @param string|null $name
     * @return bool
     */
    public static function exists(string|null $name = null): bool
    {
        return file_exists(FileStd::STP . FS . $name . FileStd::EXT);
    }

    /**
     * @param string|null $name
     * @return Pipe|false
     */
    public static function link(string|null $name = null): Pipe|false
    {
        if (Pipe::exists($name)) {
            return new Pipe($name, filesize(PP_RUNTIME_PATH . FS . $name . '.pipe'));
        }
        return false;
    }

    /**
     * @param string $context
     * @param ?int   $start
     * @return int|false
     */
    public function write(string $context, int|null $start = 0): int|false
    {
        if (strlen($context) < 1) {
            return false;
        }

        if ($start === 0) {
            $this->flush();
        }
        $this->adjustPoint($start);
        $this->eof += strlen($context) - $start;
        return fwrite($this->resource, $context);
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        $this->eof = -1;
        $this->adjustPoint(0);
        return ftruncate($this->resource, 0);
    }

    /**
     * @param int $location
     * @return void
     */
    private function adjustPoint(int $location): void
    {
        $this->point = $location;
        fseek($this->resource, $this->point);
    }

    /**
     * @param string $content
     * @return int
     */
    public function push(string $content): int
    {
        $this->adjustPoint($this->eof);
        $this->eof += strlen($content);
        fwrite($this->resource, $content);
        return $this->eof;
    }

    /**
     * @return string|false
     */
    public function read(): string|false
    {
        return $this->section(0);
    }

    /**
     * @param int $start
     * @param int $eof
     * @return string|false
     */
    public function section(int $start, int $eof = 0): string|false
    {
        if ($eof === 0) {
            $eof = $this->eof - $start;
        }

        if ($eof > $this->eof || $eof < $start) {
            return false;
        }

        $this->adjustPoint($start);
        $length  = $eof - $start + 1;
        $context = '';

        while ($length > 0) {
            if ($length > 8192) {
                $context .= fread($this->resource, 8192);
                $length  -= 8192;
            } else {
                $context .= fread($this->resource, $length);
                $length  = 0;
            }
        }

        return $context;
    }

    /**
     * @param bool $wait
     * @return bool
     */
    public function lock(bool $wait = true): bool
    {
        if ($wait) {
            return flock($this->resource, LOCK_EX);
        } else {
            return flock($this->resource, LOCK_EX | LOCK_NB);
        }
    }

    /**
     * @return bool
     */
    public function unlock(): bool
    {
        return flock($this->resource, LOCK_UN);
    }

    /**
     * @return Pipe
     */
    public function clone(): Pipe
    {
        return new self($this->name, $this->eof);
    }

    /**
     * @return void
     */
    public function close(): void
    {
        fclose($this->resource);
    }

    /**
     * @return void
     */
    public function release(): void
    {
        if (!get_resource_type($this->resource) == 'Unknown') {
            fclose($this->resource);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
