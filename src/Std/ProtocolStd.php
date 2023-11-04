<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Std;

use Cclilshy\PRipple\Service\Client;
use stdClass;

interface ProtocolStd
{
    /**
     * 报文打包
     * @param string $context 报文具体
     * @return string 包
     */
    public function build(string $context): string;

    /**
     * 通过协议发送
     * @param TunnelStd $tunnel
     * @param string    $context
     * @return bool
     */
    public function send(TunnelStd $tunnel, string $context): bool;

    /**
     * 报文验证
     * @param string $context 报文
     * @param stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public function verify(string $context, stdClass|null $Standard = null): string|false;

    /**
     * 报文切片
     * @param TunnelStd $tunnel 任意通道
     * @return string|false 切片结果
     */
    public function cut(TunnelStd $tunnel): string|false;

    /**
     * 抛弃脏数据，调整通道指针
     * @param TunnelStd $tunnel
     * @return string|false
     */
    public function corrective(TunnelStd $tunnel): string|false;

    /**
     * 解析报文
     * @param string $context
     * @return string
     */
    public function parse(string $context): string;

    /**
     * 握手
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null;
}
