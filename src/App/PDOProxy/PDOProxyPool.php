<?php
declare(strict_types=1);

namespace App\PDOProxy;

use App\Facade\PDOPool;
use Core\Map\ExtendMap;
use Extends\Laravel;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\DB;
use PDO;
use PRipple;
use Socket;
use Worker\Build;
use Worker\WorkerBase;

/**
 * @method static PDOProxyWorker|null get(string $name)
 * @method static void add(string $name, PDOProxyWorker $pdoProxy)
 */
class PDOProxyPool extends WorkerBase
{
    private static PDOProxyPool $instance;
    private Manager $manager;
    private array   $pool = [];

    /**
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return call_user_func_array([PDOProxyPool::instance(), $name], $arguments);
    }

    /**
     * @return PDOProxyPool|WorkerBase
     */
    public static function instance(): PDOProxyPool|WorkerBase
    {
        return PDOProxyPool::$instance;
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        foreach ($this->pool as $pdoProxy) {
            $pdoProxy->destroy();
        }
    }

    /**
     * @return void
     */
    protected function initialize(): void
    {
        PDOPool::setInstance($this);
        PDOProxyPool::$instance = $this;
        $this->manager = new Manager;
        $this->manager->bootEloquent();
        $this->manager->setAsGlobal();
        ExtendMap::get(Laravel::class)->container->bind('db', function (Container $container) {
            return $this->manager->getDatabaseManager();
        });
        DB::setFacadeApplication(ExtendMap::get(Laravel::class)->container);
    }

    /**
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }

    /**
     * @param array       $config
     * @param string|null $name
     * @return PDOProxyWorker
     */
    public function add(array $config, string|null $name = 'default'): PDOProxyWorker
    {
        $config['dsn']                                   = $config['driver'] . ':host=' . $config['hostname'] . ';dbname=' . $config['database'];
        $config['options'][PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_OBJ;
        $proxy                                           = PDOProxyWorker::new($name)->config($config);
        $this->pool[$name]                               = $proxy;
        $this->manager->addConnection([
            'driver'    => $config['driver'],
            'charset'   => $config['charset'] ?? 'utf8',
            'collation' => $config['collation'] ?? 'utf8_general_ci',
            'prefix'    => $config['prefix'] ?? '',
        ], $name);
        PRipple::kernel()->push($proxy);
        return $proxy;
    }

    /**
     * @param string|null $name
     * @return PDOProxyWorker|null
     */
    public function get(string|null $name = 'default'): PDOProxyWorker|null
    {
        return $this->pool[$name] ?? null;
    }
}
