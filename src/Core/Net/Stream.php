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

use Cclilshy\PRipple\Core\Standard\StreamInterface;
use Socket;
use function fwrite;
use function fclose;
use function stream_get_meta_data;
use function get_resource_type;
use function socket_import_stream;
use function str_replace;
use function get_resource_id;
use function fseek;
use function ftell;
use function feof;
use function filesize;

class Stream implements StreamInterface
{
    /**
     * @var resource
     */
    public mixed $stream;

    /**
     * @var Socket $socket
     */
    public Socket $socket;

    /**
     * @var string $address
     */
    public string $address;

    /**
     * @var string $path
     */
    public string $path;

    /**
     * @var int $id
     */
    public int $id;

    /**
     * @var array $meta
     */
    public array $meta;

    /**
     * Stream constructor.
     * @param mixed $resource
     */
    public function __construct(mixed $resource)
    {
        $this->stream = $resource;
        $this->meta   = stream_get_meta_data($resource);
        if (get_resource_type($resource) === 'stream') {
            if ($this->meta['stream_type'] === 'unix_socket') {
                $this->socket = socket_import_stream($resource);
                if ($this->meta['uri']) {
                    $this->path = str_replace('unix://', '', $this->meta['uri']);
                }
            } elseif ($this->meta['stream_type'] === 'tcp_socket') {
                $this->socket = socket_import_stream($resource);
                if ($this->meta['uri']) {
                    $this->path = str_replace('tcp://', '', $this->meta['uri']);
                }
            } elseif ($this->meta['stream_type'] === 'tcp_socket/ssl') {
                $this->socket = socket_import_stream($resource);
                if ($this->meta['uri']) {
                    $this->path = str_replace('ssl://', '', $this->meta['uri']);
                }
            } elseif (isset($this->meta['wrapper_type']) && $this->meta['wrapper_type'] === 'plainfile') {
                $this->path = $this->meta['uri'];
            }
        } else {
            $this->path = $this->meta['uri'];
        }
        $this->id = get_resource_id($this->stream);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return '';
    }

    /**
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        $result = fread($this->stream, $length);
        if ($result === false) {
            return '';
        }
        return $result;
    }

    /**
     * @param string $string
     * @return int
     */
    public function write(string $string): int
    {
        $result = fwrite($this->stream, $string);
        if ($result === false) {
            return 0;
        }
        return $result;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        fclose($this->stream);
    }

    public function detach()
    {
        // TODO: Implement detach() method.
    }


    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        fseek($this->stream, $offset, $whence);
    }

    public function tell(): int
    {
        return ftell($this->stream);
    }

    public function eof(): bool
    {
        return feof($this->stream);
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return filesize($this->path);
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public function getMetadata(string|null $key = null): mixed
    {
        if ($key) {
            return stream_get_meta_data($this->stream)[$key];
        } else {
            return stream_get_meta_data($this->stream);
        }
    }

    /**
     * @return bool
     */
    public function isSeekable(): bool
    {
        return $this->getMetadata('seekable');
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isSocket(): bool
    {
        return isset($this->socket);
    }
}
