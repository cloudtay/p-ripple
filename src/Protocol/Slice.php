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

namespace Protocol;

use Core\FileSystem\FileException;
use Core\Std\ProtocolStd;
use stdClass;
use Worker\Socket\TCPConnection;

/**
 * 一个小而简的报文切割器
 */
class Slice implements ProtocolStd
{
    /**
     * @param TCPConnection $tunnel
     * @param string        $context
     * @return bool|int
     * @throws FileException
     */
    public function send(TCPConnection $tunnel, string $context): bool|int
    {
        $context = Slice::build($context);
        return $tunnel->write($context);
    }

    /**
     * 报文打包
     *
     * @param string $context 报文具体
     * @return string 包
     */
    public function build(string $context): string
    {
        $contextLength = strlen($context);
        $pack          = pack('L', $contextLength);
        return $pack . $context;
    }

    /**
     * 报文验证
     *
     * @param string        $context
     * @param stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public function verify(string $context, stdClass|null $Standard = null): string|false
    {
        return false;
    }

    /**
     * @param TCPConnection $tunnel
     * @return string|false
     */
    public function corrective(TCPConnection $tunnel): string|false
    {
        return false;
    }

    /**
     * @param TCPConnection $tunnel
     * @return string|false|null
     */
    public function parse(TCPConnection $tunnel): string|null|false
    {
        return $this->cut($tunnel);
    }

    /**
     * @param TCPConnection $tunnel
     * @return string|false|null
     */
    public function cut(TCPConnection $tunnel): string|null|false
    {
        $buffer = $tunnel->cache();
        if (strlen($buffer) < 4) {
            return false;
        }
        $length = substr($buffer, 0, 4);
        $pack   = unpack('L', $length);
        $length = $pack[1];
        if (strlen($buffer) >= $length + 4) {
            $context       = substr($buffer, 4, $length);
            $tunnel->cache = substr($buffer, $length + 4);
            return $context;
        }
        return false;
    }

    /**
     * @param TCPConnection $client
     * @return bool|null
     */
    public function handshake(TCPConnection $client): bool|null
    {
        return true;
    }
}
