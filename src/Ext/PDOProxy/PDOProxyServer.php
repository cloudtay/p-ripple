<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

declare(strict_types=1);

namespace Ext\PDOProxy;

use PDO;
use Protocol\Slice;
use Worker\Built\JsonRpc\Attribute\Rpc;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * PDO代理服务端
 */
class PDOProxyServer extends Worker
{
    use JsonRpc;

    private string        $dns;
    private string        $username;
    private string        $password;
    private array         $options;
    private int           $count = 0;
    private PDO           $pdoConnection;
    private TCPConnection $server;

    /**
     * @param string $dns
     * @param string $username
     * @param string $password
     * @param array  $options
     */
    private function __construct(string $dns, string $username, string $password, array $options)
    {
        $this->dns      = $dns;
        $this->username = $username;
        $this->password = $password;
        $this->options  = $options;
        parent::__construct(PDOProxyServer::class);
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        $this->slice = new Slice();
        $this->reconnectPdo();
        parent::initialize();
    }

    /**
     * 重连PDO
     * @return void
     */
    private function reconnectPdo(): void
    {
        $this->pdoConnection = new PDO($this->dns, $this->username, $this->password, $this->options);
    }

    /**
     * 数据库查询
     * @param string $query
     * @param array  $bindings
     * @param array  $bindParams
     * @return array|false
     */
    #[Rpc('数据库查询')] public function query(string $query, array $bindings, array $bindParams): array|false
    {
        $pdoStatement = $this->pdoConnection->prepare($query);
        foreach ($bindings as $key => $value) {
            $pdoStatement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_resource($value) => PDO::PARAM_LOB,
                    default => PDO::PARAM_STR
                },
            );
        }

        foreach ($bindParams as $key => $value) {
            $pdoStatement->bindParam(
                is_string($key) ? $key : $key + 1,
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_resource($value) => PDO::PARAM_LOB,
                    default => PDO::PARAM_STR
                },
            );
        }
        if ($pdoStatement->execute()) {
            $result = $pdoStatement->fetchAll();
        } else {
            $result = false;
        }
        return $result;
    }

    /**
     * 开始数据库事务
     * @return bool
     */
    #[Rpc('开始数据库事务')] public function beginTransaction(): bool
    {
        return $this->pdoConnection->beginTransaction();
    }

    /**
     * 提交事务查询
     * @return bool
     */
    #[Rpc('提交事务查询')] public function commit(): bool
    {
        return $this->pdoConnection->commit();

    }

    /**
     * 回滚事务查询
     * @return bool
     */
    #[Rpc('回滚事务查询')] public function rollBack(): bool
    {
        return $this->pdoConnection->rollBack();
    }
}
