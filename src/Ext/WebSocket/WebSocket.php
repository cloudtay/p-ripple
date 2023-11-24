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

namespace Ext\WebSocket;

use Core\FileSystem\FileException;
use Core\Output;
use Core\Std\ProtocolStd;
use Core\Std\TunnelStd;
use Ext\WebSocket\WebSocket\Handshake;
use stdClass;
use Worker\Socket\TCPConnection;

/**
 * Websocket协议
 */
class WebSocket implements ProtocolStd
{
    /**
     * SEND VIA INTERFACE
     * @param TunnelStd $tunnel
     * @param string    $context
     * @return bool
     */
    public function send(TunnelStd $tunnel, string $context): bool
    {
        $build = WebSocket::build($context);
        return (bool)$tunnel->write($build);
    }

    /**
     * PACKET PACKING
     * @param string $context MESSAGE SPECIFIC
     * @return string TAKE CHARGE OF
     */
    public function build(string $context, int $opcode = 0x1, bool $fin = true): string
    {
        $frame      = chr(($fin ? 0x80 : 0) | $opcode); // FIN 和 Opcode
        $contextLen = strlen($context);
        if ($contextLen < 126) {
            $frame .= chr($contextLen); // Payload Length
        } elseif ($contextLen <= 0xFFFF) {
            $frame .= chr(126) . pack('n', $contextLen); // Payload Length 和 Extended payload length (2 字节)
        } else {
            $frame .= chr(127) . pack('J', $contextLen); // Payload Length 和 Extended payload length (8 字节)
        }
        $frame .= $context; // Payload Data
        return $frame;
    }

    /**
     * MESSAGE VERIFICATION
     * @param string        $context  MESSAGE
     * @param stdClass|null $Standard Additional parameters
     * @return string|false Validation results
     */
    public function verify(string $context, stdClass|null $Standard = null): string|false
    {
        //不支持校验
        return false;
    }

    /**
     * 报文切片
     * @param TunnelStd $tunnel ANY CHANNEL
     * @return string|false|null SLICE RESULT
     */
    public function cut(TunnelStd $tunnel): string|null|false
    {
        if ($tunnel instanceof TCPConnection) {
            return WebSocket::parse($tunnel);
        }
        return false;
    }

    /**
     * @param TCPConnection $tunnel
     * @return string|false|null
     */
    public function parse(TCPConnection $tunnel): string|null|false
    {
        $context       = $tunnel->cache();
        $payload       = '';
        $payloadLength = '';
        $mask          = '';
        $maskingKey    = '';
        $opcode        = '';
        $fin           = '';
        $dataLength    = strlen($context);
        $index         = 0;
        $byte          = ord($context[$index++]);
        $fin           = ($byte & 0x80) != 0;
        $opcode        = $byte & 0x0F;
        $byte          = ord($context[$index++]);
        $mask          = ($byte & 0x80) != 0;
        $payloadLength = $byte & 0x7F;

        // 处理 2 字节或 8 字节的长度字段
        if ($payloadLength > 125) {
            if ($payloadLength == 126) {
                $payloadLength = unpack('n', substr($context, $index, 2))[1];
                $index         += 2;
            } else {
                $payloadLength = unpack('J', substr($context, $index, 8))[1];
                $index         += 8;
            }
        }

        // 处理掩码密钥
        if ($mask) {
            $maskingKey = substr($context, $index, 4);
            $index      += 4;
        }

        // 处理负载数据
        $payload = substr($context, $index);
        if ($mask) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
            }
        }
        $tunnel->cleanCache();
        return $payload;
    }

    /**
     * Adjustment not supported
     * @param TunnelStd $tunnel
     * @return string|false
     */
    public function corrective(TunnelStd $tunnel): string|false
    {
        return false;
    }

    /**
     * 请求握手
     * @param TCPConnection $client
     * @return bool|null
     */
    public function handshake(TCPConnection $client): bool|null
    {
        try {
            return Handshake::accept($client);
        } catch (FileException $exception) {
            Output::printException($exception);
            return false;
        }
    }
}
