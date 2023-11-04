<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Protocol;

use Cclilshy\PRipple\Protocol\WebSocket\Handshake;
use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Std\TunnelStd;
use Cclilshy\PRipple\Worker\NetWorker\Client;
use Exception;
use stdClass;

/**
 *
 */
class WebSocket implements ProtocolStd
{
    /**
     * SEND VIA INTERFACE
     * @param TunnelStd $tunnel
     * @param string $context
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
        $frame = chr(($fin ? 0x80 : 0) | $opcode); // FIN 和 Opcode
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
     * @param string $context MESSAGE
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
     * @return string|false SLICE RESULT
     * @throws Exception
     */
    public function cut(TunnelStd $tunnel): string|false
    {
        $context = $tunnel->read(0, $resultLength);
        return WebSocket::parse($context);
    }

    /**
     * @param string $context
     * @return string
     */
    public function parse(string $context): string
    {
        $payload = '';
        $payloadLength = '';
        $mask = '';
        $maskingKey = '';
        $opcode = '';
        $fin = '';
        $dataLength = strlen($context);
        $index = 0;

        $byte = ord($context[$index++]);
        $fin = ($byte & 0x80) != 0;
        $opcode = $byte & 0x0F;
        $byte = ord($context[$index++]);
        $mask = ($byte & 0x80) != 0;
        $payloadLength = $byte & 0x7F;

        // 处理 2 字节或 8 字节的长度字段
        if ($payloadLength > 125) {
            if ($payloadLength == 126) {
                $payloadLength = unpack('n', substr($context, $index, 2))[1];
                $index += 2;
            } else {
                $payloadLength = unpack('J', substr($context, $index, 8))[1];
                $index += 8;
            }
        }

        // 处理掩码密钥
        if ($mask) {
            $maskingKey = substr($context, $index, 4);
            $index += 4;
        }

        // 处理负载数据
        $payload = substr($context, $index);
        if ($mask) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
            }
        }

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
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        return Handshake::accept($client);
    }
}
