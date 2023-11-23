<?php
declare(strict_types=1);

namespace Protocol;

use Core\FileSystem\FileException;
use Core\Std\ProtocolStd;
use stdClass;
use Worker\Socket\TCPConnection;

/**
 * 一个小而简的报文切割器
 */
class CCL implements ProtocolStd
{
    /**
     * @param TCPConnection $tunnel
     * @param string        $context
     * @return bool|int
     * @throws FileException
     */
    public function send(TCPConnection $tunnel, string $context): bool|int
    {
        $context = CCL::build($context);
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
