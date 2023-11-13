<?php
declare(strict_types=1);

namespace Protocol;

use FileSystem\FileException;
use Std\ProtocolStd;
use stdClass;
use Worker\NetWorker\Client;

/**
 * 一个小而简的报文切割器
 */
class CCL implements ProtocolStd
{
    /**
     * @param Client $tunnel
     * @param string $context
     * @return bool|int
     * @throws FileException
     */
    public function send(Client $tunnel, string $context): bool|int
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
        $pack = pack('L', $contextLength);
        return $pack . $context;
    }

    /**
     * 报文验证
     *
     * @param string $context
     * @param stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public function verify(string $context, stdClass|null $Standard = null): string|false
    {
        return false;
    }

    /**
     * @param Client $tunnel
     * @return string|false
     */
    public function corrective(Client $tunnel): string|false
    {
        // TODO: Implement corrective() method.
        return false;
    }

    /**
     * @param Client $tunnel
     * @return string|false|null
     */
    public function parse(Client $tunnel): string|null|false
    {
        // TODO: Implement parse() method.
        return $this->cut($tunnel);
    }

    /**
     * @param Client $tunnel
     * @return string|false|null
     */
    public function cut(Client $tunnel): string|null|false
    {
        $buffer = $tunnel->cache();
        if (strlen($buffer) < 4) {
            return false;
        }
        $length = substr($buffer, 0, 4);
        $pack = unpack('L', $length);
        $length = $pack[1];
        if (strlen($buffer) >= $length + 4) {
            $context = substr($buffer, 4, $length);
            $tunnel->cache = substr($buffer, $length + 4);
            return $context;
        }
        return false;
    }

    /**
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        // TODO: Implement handshake() method.
        return true;
    }
}
