<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Protocol;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Std\ProtocolStd;
use Exception;
use stdClass;

class CCL implements ProtocolStd
{
    /**
     * @param Client $tunnel
     * @param string $context
     * @return bool|int
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
     * @param \stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public function verify(string $context, stdClass|null $Standard = null): string|false
    {
        return false;
    }

    /**
     * @throws Exception
     */
    public function cutWithString($aisle, &$string): string|false
    {
        if ($context = CCL::cut($aisle)) {
            if ($intPack = substr($context, 0, 64)) {
                if ($pack = unpack('A64', $intPack)) {
                    $string = $pack[1];
                }
            }
            return $context;
        }
        return false;
    }

    /**
     * @param Client $tunnel
     * @return string|false
     * @throws Exception
     */
    public function cut(Client $tunnel): string|false
    {
        if (!$read = $tunnel->read(0, $_)) {
            return false;
        }
        $buffer = $tunnel->cache($read);
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

    public function corrective(Client $tunnel): string|false
    {
        // TODO: Implement corrective() method.
        return false;
    }

    public function parse(string $context): string
    {
        // TODO: Implement parse() method.
        return '';
    }

    public function handshake(Client $client): bool|null
    {
        // TODO: Implement handshake() method.
        return true;
    }
}
