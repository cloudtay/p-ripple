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


namespace Cclilshy\PRipple\Protocol;

use Cclilshy\PRipple\Core\Net\Exception;
use Cclilshy\PRipple\Core\Standard\ProtocolStd;
use Cclilshy\PRipple\Filesystem\Exception\FileException;
use Cclilshy\PRipple\Worker\Socket\TCPConnection;
use stdClass;
use function pack;
use function strlen;
use function substr;

/**
 * @class Slice 一个小而简的报文切割器
 */
class Slice implements ProtocolStd
{
    /**
     * @param TCPConnection $TCPConnection
     * @param string        $context
     * @return bool|int
     * @throws FileException|Exception
     */
    public function send(TCPConnection $TCPConnection, string $context): bool|int
    {
        $context = Slice::build($context);
        return $TCPConnection->write($context);
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
     * @param TCPConnection $TCPConnection
     * @return string|false
     */
    public function corrective(TCPConnection $TCPConnection): string|false
    {
        return false;
    }

    /**
     * @param TCPConnection $TCPConnection
     * @return string|false|null
     */
    public function parse(TCPConnection $TCPConnection): string|null|false
    {
        return $this->cut($TCPConnection);
    }

    /**
     * 荷载状态
     * @var int $payloadStatus
     */
    private int $payloadStatus = 0;

    /**
     * 荷载长度
     * @var int $payloadLength
     */
    private int $payloadLength = 0;

    /**
     * @param TCPConnection $TCPConnection
     * @return string|false|null
     */
    public function cut(TCPConnection $TCPConnection): string|null|false
    {
        if ($this->payloadStatus === 0) {
            if (strlen($TCPConnection->buffer()) >= 4) {
                $this->payloadLength   = unpack('L', substr($TCPConnection->buffer(), 0, 4))[1];
                $TCPConnection->buffer = substr($TCPConnection->buffer(), 4);
                if (strlen($TCPConnection->buffer()) >= $this->payloadLength) {
                    $context               = substr($TCPConnection->buffer(), 0, $this->payloadLength);
                    $TCPConnection->buffer = substr($TCPConnection->buffer(), $this->payloadLength);
                    return $context;
                }
                $this->payloadStatus = 1;
            }
        } else {
            if (strlen($TCPConnection->buffer()) >= $this->payloadLength) {
                $payload               = substr($TCPConnection->buffer(), 0, $this->payloadLength);
                $TCPConnection->buffer = substr($TCPConnection->buffer(), $this->payloadLength);
                $this->payloadStatus   = 0;
                return $payload;
            }
        }
        return null;
    }

    /**
     * @param TCPConnection $client
     * @return bool|null
     */
    public function handshake(TCPConnection $client): bool|null
    {
        return true;
    }

    /**
     * @param mixed|null $config
     */
    public function __construct(mixed $config = null)
    {
    }

    /**
     * @return void
     */
    public function __clone(): void
    {
        $this->payloadStatus = 0;
        $this->payloadLength = 0;
    }
}
