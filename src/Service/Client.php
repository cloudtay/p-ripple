<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Service;

use AllowDynamicProperties;
use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Tunnel\SocketAisle;
use stdClass;

/**
 *
 */
#[AllowDynamicProperties] class Client extends SocketAisle
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
     * @return string|false
     */
    public function getPlaintext(): string|false
    {
        if ($context = $this->read(0, $resultLength)) {
            if ($result = $this->protocol->parse($this->cache($context))) {
                $this->cleanCache();
            }
            return $result;
        }
        return $context;
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
     * @return void
     */
    public function cleanCache(): void
    {
        $this->cache = '';
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
            return boolval($this->write($context));
        }
    }
}
