<?php
declare(strict_types=1);

namespace Protocol;

use Core\Std\ProtocolStd;
use Core\Std\TunnelStd;
use stdClass;
use Worker\Socket\TCPConnection;

/**
 * TCP协议
 */
class TCPProtocol implements ProtocolStd
{
    /**
     * @param string $context
     * @return string
     */
    public function build(string $context): string
    {
        return $context;
    }

    /**
     * @param TunnelStd $tunnel
     * @param string    $context
     * @return bool|int
     */
    public function send(TunnelStd $tunnel, string $context): bool|int
    {
        return $tunnel->write($context);
    }

    /**
     * @param string        $context
     * @param stdClass|null $Standard
     * @return string|false
     */
    public function verify(string $context, ?stdClass $Standard = null): string|false
    {
        return false;
    }

    /**
     * @param TunnelStd $tunnel
     * @return string|false
     */
    public function corrective(TunnelStd $tunnel): string|false
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
     * @param TunnelStd $tunnel
     * @return string|false
     */
    public function cut(TunnelStd $tunnel): string|false
    {
        if ($tunnel instanceof TCPConnection) {
            return $tunnel->cleanCache();
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
