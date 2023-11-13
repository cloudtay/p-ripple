<?php
declare(strict_types=1);

namespace App\PDOProxy;

use Worker\Build;

/**
 * PDO请求包体
 */
class PDOBuild extends Build
{
    public const EVENT_QUERY = 'pdo.proxy.query';
    public const EVENT_BEGIN_TRANSACTION = 'pdo.proxy.beginTransaction';
    public const EVENT_COMMIT = 'pdo.proxy.commit';
    public const EVENT_ROLL_BACK = 'pdo.proxy.rollBack';
    public const EVENT_TRANSACTION = 'pdo.proxy.transaction';


    /**
     * @var string|mixed
     */
    public string $query;

    /**
     * @var array|mixed
     */
    public array $bindings;

    /**
     * @var array|mixed
     */
    public array $bindParams;

    /**
     * @param string $name
     * @param mixed  $data
     * @param string $hash
     */
    public function __construct(string $name, mixed $data, string $hash)
    {
        parent::__construct($name, $data, $hash);
        if ($name === PDOBuild::EVENT_QUERY) {
            $this->query = $data['query'];
            $this->bindings = $data['bindings'];
            $this->bindParams = $data['bindParams'];
        }
    }

    /**
     * @param string $hash
     * @return PDOBuild
     */
    public static function beginTransaction(string $hash): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_BEGIN_TRANSACTION, null, $hash);
    }

    /**
     * @param string $hash
     * @param string $query
     * @param array|null $bindings
     * @param array|null $bindParams
     * @return PDOBuild
     */
    public static function query(string $hash, string $query, array|null $bindings = [], array|null $bindParams = []): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_QUERY, [
            'query' => $query,
            'bindings' => $bindings,
            'bindParams' => $bindParams
        ], $hash);
    }

    /**
     * @param string $hash
     * @return PDOBuild
     */
    public static function commit(string $hash): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_COMMIT, null, $hash);
    }

    /**
     * @param string $hash
     * @return PDOBuild
     */
    public static function rollBack(string $hash): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_ROLL_BACK, null, $hash);
    }
}
