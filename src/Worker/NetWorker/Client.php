<?php
declare(strict_types=1);

namespace Worker\NetWorker;

use AllowDynamicProperties;
use Core\Output;
use FileSystem\FileException;
use Std\ProtocolStd;
use stdClass;
use Worker\NetWorker\Tunnel\SocketTunnel;

/**
 * 客户端
 */
#[AllowDynamicProperties] class Client extends SocketTunnel
{
    public string $verifyBuffer;
    public string $socketType;
    public bool $verify;
    public string $cache;
    public mixed $info;
    public ProtocolStd $protocol;

    /**
     * @param mixed $socket
     * @param string $type
     */
    public function __construct(mixed $socket, string $type)
    {
        parent::__construct($socket);
        $this->socketType = $type;
        $this->verifyBuffer = '';
        $this->verify = false;
        $this->info = new stdClass();
        $this->cache = '';
    }

    /**
     * 设置协议
     * @param ProtocolStd $protocol
     * @return true
     */
    public function handshake(ProtocolStd $protocol): true
    {
        $this->protocol = $protocol;
        return $this->verify = true;
    }

    /**
     * 通过协议切割
     * @return string|false|null
     */
    public function getPlaintext(): string|null|false
    {
        return $this->protocol->parse($this);
    }

    /**
     * 客户端数据缓存区
     * @param string|null $context
     * @return string
     */
    public function cache(string|null $context = null): string
    {
        if ($context !== null) {
            $this->cache .= $context;
        }
        return $this->cache;
    }

    /**
     * 清空缓存区
     * @return string
     */
    public function cleanCache(): string
    {
        $cache = $this->cache;
        $this->cache = '';
        return $cache;
    }

    /**
     * 发送信息
     * @param string $context
     * @return bool|int
     */
    public function send(string $context): bool|int
    {
        if (isset($this->protocol)) {
            return $this->protocol->send($this, $context);
        } else {
            try {
                return boolval($this->write($context));
            } catch (FileException $exception) {
                Output::printException($exception);
                return false;
            }
        }
    }
}
