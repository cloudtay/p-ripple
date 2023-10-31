<?php

namespace Cclilshy\PRipple\Protocol;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Std\TunnelStd;
use stdClass;

class TCPProtocol implements ProtocolStd
{
    public function build(string $context): string
    {
        return $context;
    }

    public function send(TunnelStd $tunnel, string $context): bool
    {
        return $tunnel->write($context);
    }

    public function verify(string $context, ?stdClass $Standard = null): string|false
    {
        return false;
    }

    public function cut(TunnelStd $tunnel): string|false
    {
        return $tunnel->read(0, $_);
    }

    public function corrective(TunnelStd $tunnel): string|false
    {
        return false;
    }

    public function parse(string $context): string
    {
        return $context;
    }

    public function handshake(Client $client): bool|null
    {
        return true;
    }
}
