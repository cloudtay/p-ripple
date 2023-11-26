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

namespace Support\PDOProxy;

use PDO;
use Protocol\TCPProtocol;
use Worker\Built\JsonRpc\Attribute\Rpc;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Worker;

class PDOProxy extends Worker
{
    use JsonRpc;

    private PDO  $pdo;
    private bool $isTransaction = false;

    /**
     * PDOProxy constructor.
     * @param string      $name
     * @param string|null $protocol
     */
    public function __construct(string $name, ?string $protocol = TCPProtocol::class)
    {
        parent::__construct($name, $protocol);
    }

    /**
     * Connect native PDO
     * @param array $config
     * @return $this
     */
    public function connect(array $config): PDOProxy
    {
        $this->pdo = new PDO(
            "{$config['driver']}:host={$config['hostname']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
            $config['options']
        );
        return $this;
    }

    /**
     * 数据库查询
     * @param string     $query
     * @param array|null $bindings
     * @param array|null $bindParams
     * @return false|array
     */
    #[Rpc('数据库查询')] public function prepare(string $query, array|null $bindings = [], array|null $bindParams = []): false|array
    {
        $pdoStatement = $this->pdo->prepare($query);
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
        if (!$this->isTransaction) {
            return $this->isTransaction = $this->pdo->beginTransaction();
        }
        return false;
    }

    /**
     * 提交事务查询
     * @return bool
     */
    #[Rpc('提交事务查询')] public function commit(): bool
    {
        if ($this->pdo->commit()) {
            $this->isTransaction = false;
            return true;
        }
        return false;
    }

    /**
     * 回滚事务查询
     * @return bool
     */
    #[Rpc('回滚事务查询')] public function rollBack(): bool
    {
        if ($this->pdo->rollBack()) {
            $this->isTransaction = false;
            return true;
        }
        return false;
    }
}
