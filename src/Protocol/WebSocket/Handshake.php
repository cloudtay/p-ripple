<?php
declare(strict_types=1);

namespace Protocol\WebSocket;

use FileSystem\FileException;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketAisleException;


/**
 * Websocket握手处理器
 */
class Handshake
{
    /**
     * Attempts to recognize handshake data when receiving a client for the first time
     * @param Client $client
     * @return bool
     */

    public const NEED_HEAD = [
        'Host' => true,
        'Upgrade' => true,
        'Connection' => true,
        'Sec-WebSocket-Key' => true,
        'Sec-WebSocket-Version' => true
    ];

    /**
     * @param Client $client
     * @return bool|null
     * @throws FileException
     * @throws SocketAisleException
     */
    public static function accept(Client $client): bool|null
    {
        $context = $client->read(0, $resultLength);
        $buffer = $client->cache($context);
        $identityInfo = Handshake::verify($buffer);
        if ($identityInfo === null) {
            return null;
        } elseif ($identityInfo === false) {
            return false;
        } else {
            $client->info = $identityInfo;
            $secWebSocketAccept = Handshake::getSecWebSocketAccept($client->info['Sec-WebSocket-Key']);
            $client->write(Handshake::generateResultContext($secWebSocketAccept));
            $client->cleanCache();
            return true;
        }
    }

    /**
     * 验证信息
     * @param string $buffer
     * @return array|false|null
     */
    public static function verify(string $buffer): array|false|null
    {
        if (str_contains($buffer, "\r\n\r\n")) {
            $verify = Handshake::NEED_HEAD;
            $lines = explode("\r\n", $buffer);
            $header = array();

            if (count($firstLineInfo = explode(" ", array_shift($lines))) !== 3) {
                return false;
            } else {
                $header['method'] = $firstLineInfo[0];
                $header['url'] = $firstLineInfo[1];
                $header['version'] = $firstLineInfo[2];
            }

            foreach ($lines as $line) {
                if ($_ = explode(":", $line)) {
                    $header[trim($_[0])] = trim($_[1] ?? '');
                    unset($verify[trim($_[0])]);
                }
            }

            if (count($verify) > 0) {
                return false;
            } else {
                return $header;
            }
        } else {
            return null;
        }
    }

    /**
     * @param string $key
     * @return string
     */
    private static function getSecWebSocketAccept(string $key): string
    {
        return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }

    /**
     * @param string $accept
     * @return string
     */
    private static function generateResultContext(string $accept): string
    {
        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $accept
        ];
        $context = "HTTP/1.1 101 NFS\r\n";
        foreach ($headers as $key => $value) {
            $context .= "{$key}: {$value} \r\n";
        }
        $context .= "\r\n";
        return $context;
    }
}
