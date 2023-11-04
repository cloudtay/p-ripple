<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Protocol;

use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Worker\NetWorker\Client;
use stdClass;

class RESPProtocol implements ProtocolStd
{
    public function build(string $context): string
    {
        // TODO: Implement build() method.
    }

    public function send(Client $tunnel, string $context): bool|int
    {
        // TODO: Implement send() method.
    }

    public function verify(string $context, ?stdClass $Standard = null): string|false
    {
        // TODO: Implement verify() method.
    }

    public function cut(Client $tunnel): string|false
    {
        // TODO: Implement cut() method.
    }

    public function corrective(Client $tunnel): string|false
    {
        // TODO: Implement corrective() method.
    }

    public function parse(string $context): string
    {
        // TODO: Implement parse() method.
    }

    public function handshake(Client $client): bool|null
    {
        // TODO: Implement handshake() method.
    }
}