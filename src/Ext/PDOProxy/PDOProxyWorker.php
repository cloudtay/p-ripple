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

use Closure;
use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Ext\PDOProxy\Exception\CommitException;
use Ext\PDOProxy\Exception\PDOProxyException;
use Ext\PDOProxy\Exception\PDOProxyExceptionBuild;
use Fiber;
use PRipple;
use Protocol\Slice;
use Throwable;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * PDOWorker
 */
class PDOProxyWorker extends Worker
{
    public static string $unixPath;
    public array         $proxyProcessIds   = [];
    public int           $proxyProcessCount = 0;

    /**
     * @var PDOBuild[] $queue
     */
    public array  $queue   = [];
    private array $bindMap = [];
    /**
     * @var PDOProxyClient[] $connections
     */
    private array $connections = [];
    private array $config;

    /**
     * @return PDOProxyWorker|Worker
     */
    public static function instance(): PDOProxyWorker|Worker
    {
        return WorkerMap::getWorker(PDOProxyWorker::class);
    }

    /**
     * @param array $config
     * @return $this
     */
    public function config(array $config): PDOProxyWorker
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @param Closure $callable
     * @return void
     */
    public function transaction(Closure $callable): void
    {
        if ($connection = $this->getConnection()) {
            try {
                $connection->pushBeginTransaction(CollaborativeFiberMap::current()->hash);
            } catch (FileException $exception) {
                CollaborativeFiberMap::current()->exceptionHandler(new PDOProxyException('No available connections'));
            }
            if ($this->waitResponse() === true) {
                $transaction = new PDOTransaction($connection, $this);
                try {
                    call_user_func($callable, $transaction);
                    $transaction->_commit();
                } catch (CommitException $exception) {
                    $transaction->_commit();
                } catch (Throwable $exception) {
                    try {
                        $transaction->_rollback();
                    } catch (Throwable $exception) {
                        Output::printException($exception);
                    }
                }
                $connection->transaction = false;
            }
        } else {
            CollaborativeFiberMap::current()->exceptionHandler(new PDOProxyException('No available connections'));
        }
    }

    /**
     * @param string|null $hash
     * @return PDOProxyClient|false
     */
    public function getConnection(string|null $hash = null): PDOProxyClient|false
    {
        if ($hash) {
            return $this->bindMap[$hash] ?? false;
        }

        $freeConnections = array_filter($this->connections, function (PDOProxyClient $connection) {
            return $connection->transaction === false;
        });

        if (empty($freeConnections)) {
            return false;
        }

        $counts             = array_column($freeConnections, 'count');
        $connectionsByCount = array_combine($counts, $freeConnections);
        $connection         = $connectionsByCount[min($counts)];
        $connection->count++;
        return $connection;
    }

    /**
     * @return mixed
     */
    public function waitResponse(): mixed
    {
        try {
            $response = Fiber::suspend(Build::new('suspend', null, PDOProxyWorker::class));
        } catch (Throwable $exception) {
            CollaborativeFiberMap::current()->exceptionHandler(new PDOProxyException('No available connections'));
            return false;
        }
        if ($response instanceof PDOProxyExceptionBuild) {
            CollaborativeFiberMap::current()->exceptionHandler(
                new PDOProxyException($response->message, $response->code, $response->previous)
            );
            try {
                Fiber::suspend(Build::new('suspend', null, PDOProxyWorker::class));
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
        return $response;
    }

    /**
     * @param int $num
     * @return int
     */
    public function activate(int $num): int
    {
        if ($num < 1) {
            return 0;
        }
        $result = 0;
        foreach (range(1, $num) as $ignored) {
            $pid = $this->fork();
            if ($pid) {
                $this->proxyProcessCount++;
                $result++;
            }
        }
        return $result;
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        while ($queue = array_shift($this->queue)) {
            if ($connection = $this->getConnection()) {
                switch ($queue->name) {
                    case PDOBuild::EVENT_QUERY:
                        $connection->pushQuery($queue->source, $queue->query, $queue->bindings, $queue->bindParams);
                        break;
                    default:
                }
            } else {
                array_unshift($this->queue, $queue);
                return;
            }
        }
        $this->busy = false;
    }

    /**
     * @param string     $query
     * @param array|null $bindValues
     * @param array|null $bindParams
     * @return mixed
     */
    public function query(string $query, array|null $bindValues = [], array|null $bindParams = []): mixed
    {
        $hash = CollaborativeFiberMap::current()->hash;
        if ($connection = $this->getConnection()) {
            $connection->pushQuery($hash, $query, $bindValues, $bindParams);
        } else {
            $this->queue[] = PDOBuild::query($hash, $query, $bindValues, $bindParams);
            $this->busy    = true;
        }
        return $this->waitResponse();

    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
        $proxyName = PRipple::uniqueHash();
        $client->setName($proxyName);
        $this->connections[$proxyName] = new PDOProxyClient($client);
        Output::info('    - PDOPRoxy-online:' . $proxyName);
        parent::onConnect($client);
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function splitMessage(TCPConnection $client): void
    {
        while ($content = $client->getPlaintext()) {
            $this->onMessage($content, $client);
        }
    }

    /**
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        /**
         * @var PDOBuild $event
         */
        $event = unserialize($context);
        $this->resume($event->source, $event->data);
        $this->connections[$client->getName()]->count--;
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        PDOProxyWorker::$unixPath = PP_RUNTIME_PATH . '/p_ripple_pdo_proxy.sock';
        file_exists(PDOProxyWorker::$unixPath) && unlink(PDOProxyWorker::$unixPath);
        $this->bind('unix://' . PDOProxyWorker::$unixPath);
        $this->protocol(Slice::class);
        parent::initialize();
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
        $this->proxyProcessCount--;
        unset($this->connections[$client->getName()]);
    }
}
