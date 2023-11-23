<?php
declare(strict_types=1);

namespace Core\Std;

use stdClass;
use Worker\Socket\TCPConnection;

/**
 * 协议标准
 */
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
     * @param TCPConnection $tunnel
     * @param string        $context
     * @return bool|int
     */
    public function send(TCPConnection $tunnel, string $context): bool|int;

    /**
     * 报文验证
     * @param string        $context  报文
     * @param stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public function verify(string $context, stdClass|null $Standard = null): string|false;

    /**
     * 报文切片
     * @param TCPConnection $tunnel 任意通道
     * @return string|false|null 切片结果
     */
    public function cut(TCPConnection $tunnel): string|null|false;

    /**
     * 抛弃脏数据，调整通道指针
     * @param TCPConnection $tunnel
     * @return string|false
     */
    public function corrective(TCPConnection $tunnel): string|false;

    /**
     * 解析报文
     * @param TCPConnection $tunnel
     * @return string|false|null
     */
    public function parse(TCPConnection $tunnel): string|null|false;

    /**
     * 握手
     * @param TCPConnection $client
     * @return bool|null
     */
    public function handshake(TCPConnection $client): bool|null;
}
