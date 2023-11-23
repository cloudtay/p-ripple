<?php
declare(strict_types=1);

namespace recycle;

use PDO;
use Protocol\CCL;
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
    private CCL           $ccl;

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
        parent::__construct();
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        $this->ccl = new CCL();
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
