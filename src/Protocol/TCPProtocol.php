<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Protocol;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Std\TunnelStd;
use stdClass;

/**
 *
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
     * @param string $context
     * @return bool
     */
    public function send(TunnelStd $tunnel, string $context): bool
    {
        return $tunnel->write($context);
    }

    /**
     * @param string $context
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
    public function cut(TunnelStd $tunnel): string|false
    {
        return $tunnel->read(0, $_);
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
     * @param string $context
     * @return string
     */
    public function parse(string $context): string
    {
        return $context;
    }

    /**
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        return true;
    }
}
