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


namespace Cclilshy\PRipple\Worker\Socket;

use Cclilshy\PRipple\Core\Net\Socket;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\ProtocolStd;
use Cclilshy\PRipple\Filesystem\Exception\FileException;
use Cclilshy\PRipple\Protocol\TCPProtocol;
use Exception;
use Generator;
use function boolval;

/**
 * @class TCPConnection 客户端
 */
class TCPConnection extends Socket
{
    public bool        $verify = false;
    public string      $buffer = '';
    public mixed       $info   = null;
    public ProtocolStd $protocol;

    /**
     * @param resource $stream
     * @throws Exception
     */
    public function __construct(mixed $stream, ProtocolStd|string|null $protocol = TCPProtocol::class)
    {
        parent::__construct($stream);
        $this->protocol($protocol);
    }

    /**
     * 设置协议
     * @param ProtocolStd|string|null $protocol
     * @return void
     */
    public function protocol(ProtocolStd|string|null $protocol = TCPProtocol::class): void
    {
        if ($protocol instanceof ProtocolStd) {
            $this->protocol = $protocol;
        } else {
            $this->protocol = new $protocol($this->info);
        }
    }

    /**
     * 确认握手
     * @param ProtocolStd|null $protocolStd
     * @return void
     */
    public function handshake(ProtocolStd|null $protocolStd = null): void
    {
        if ($protocolStd) {
            $this->protocol = $protocolStd;
        }
        $this->verify = true;
    }


    /**
     * 发送信息
     * @param string $context
     * @return bool|int
     */
    public function send(string $context): bool|int
    {
        if (isset($this->protocol)) {
            return $this->protocol->send($this, $context);
        } else {
            try {
                return boolval($this->write($context));
            } catch (Exception|FileException $exception) {
                Output::error($exception);
                return false;
            }
        }
    }

    /**
     * 客户端数据缓存区
     * @param string|null $context
     * @return string
     */
    public function buffer(string|null $context = null): string
    {
        if ($context !== null) {
            $this->buffer .= $context;
        }
        return $this->buffer;
    }

    /**
     * 清空缓存区
     * @return string
     */
    public function cleanBuffer(): string
    {
        $cache        = $this->buffer;
        $this->buffer = '';
        return $cache;
    }

    /**
     * 读取到缓冲区
     * @return int|false
     */
    public function readToBuffer(): string|false
    {
        if (!$context = $this->read(0)) {
            return false;
        }
        return $this->buffer($context);
    }

    /**
     * 通过协议切割
     * @return Generator
     */
    public function generatePayload(): Generator
    {
        while (true) {
            yield $result = $this->protocol->cut($this);
            if ($result === false || $result === null) {
                break;
            }
        }
    }

    /**
     * 是否已经握手
     * @return bool
     */
    public function isHandshake(): bool
    {
        return $this->verify;
    }
}
