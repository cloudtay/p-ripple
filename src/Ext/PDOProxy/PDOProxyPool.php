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

use Core\Map\ExtendMap;
use Ext\WebApplication\Extends\Laravel;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\DB;
use PDO;
use PRipple;
use Socket;
use Worker\Prop\Build;
use Worker\Worker;

/**
 * @method static PDOProxyWorker|null get(string $name)
 * @method static void add(string $name, PDOProxyWorker $pdoProxy)
 */
class PDOProxyPool extends Worker
{
    private static PDOProxyPool $instance;
    private Manager             $manager;
    private array               $pool = [];

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
     * @return PDOProxyPool|Worker
     */
    public static function instance(): PDOProxyPool|Worker
    {
        return PDOProxyPool::$instance;
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void
    {
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
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
    public function initialize(): void
    {
        PDOPool::setInstance($this);
        PDOProxyPool::$instance = $this;
        $this->manager          = new Manager();
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
    public function handleEvent(Build $event): void
    {
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
