<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\Redis;

/**
 *
 */
class RedisAuthorize
{
    private string $host;
    private int $port;
    private string $password;
    private int $database;

    /**
     * @param string $host
     * @param int $port
     * @param string $password
     * @param int $database
     */
    public function __construct(string $host, int $port, string $password, int $database)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
    }

    /**
     * 构建 Redis GET 命令
     * @param string $key 键
     * @return string 返回符合 Redis 协议的 GET 命令字符串
     */
    public function buildGetCommand(string $key): string
    {
        return "*2\r\n$3\r\nGET\r\n$" . strlen($key) . "\r\n$key\r\n";
    }

    /**
     * @param string $key
     * @param string $value
     * @return string
     */
    public function buildSetCommand(string $key, string $value): string
    {
        return "*3\r\n$3\r\nSET\r\n$" . strlen($key) . "\r\n$key\r\n$" . strlen($value) . "\r\n$value\r\n";
    }

    /**
     * @param string $response
     * @return mixed
     */
    public function parseResponse(string $response): mixed
    {
        $response = explode("\r\n", $response);
        $response = array_slice($response, 1, -1);
        return array_map(function ($item) {
            if (is_numeric($item)) {
                return intval($item);
            }
            return $item;
        }, $response);
    }
}
