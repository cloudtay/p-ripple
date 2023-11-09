<?php

namespace App\PDOProxy;

use App\PDOProxy\Exception\PDOProxyExceptionBuild;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use PDO;
use PRipple;
use Protocol\CCL;
use Worker\NetWorker\Client;
use Worker\NetWorker\SocketType\SocketUnix;

class PDOProxyTransfer
{
    private string $dns;
    private string $username;
    private string $password;
    private array $options;
    private int $count = 0;

    private PDO $pdoConnection;
    private Client $server;
    private CCL $ccl;

    private function __construct(string $dns, string $username, string $password, array $options)
    {
        $this->dns = $dns;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->ccl = new CCL;
        $this->reconnectPdo();
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
     * @param string $dns
     * @param string $username
     * @param string $password
     * @param array $options
     * @return void
     */
    #[NoReturn] public static function launch(string $dns, string $username, string $password, array $options): void
    {
        $pdoProxy = new PDOProxyTransfer($dns, $username, $password, $options);
        $pdoProxy->connectServer();
        try {
            $pdoProxy->loop();
        } catch (Exception $exception) {
            //TODO: 开发时意料之外的所有异常直接关闭进程,由管理器重建代理
            exit;
        }
    }

    /**
     * 连接服务
     * @return void
     */
    private function connectServer(): void
    {
        try {
            $this->server = new Client(SocketUnix::connect(PDOProxyWorker::$UNIX_PATH, null, ['nonblock' => true]), SocketUnix::class);
        } catch (Exception $exception) {
            PRipple::printExpect($exception);
            exit;
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    #[NoReturn] private function loop(): void
    {
        while (true) {
            $readSocketList = [$this->server->getSocket()];
            $writeSocketList = [];
            if ($this->server->openCache) {
                $writeSocketList = [$this->server->getSocket()];
            }
            if (strlen($this->server->cache) > 0 || socket_select($readSocketList, $writeSocketList, $writeSocketList, 1)) {
                foreach ($writeSocketList as $ignored) {
                    while ($this->server->write('')) {
                        //TODO:循环清空缓存
                    }
                }
                foreach ($readSocketList as $ignored) {
                    $context = $this->server->read(0, $_);
                    if ($context === false) {
                        exit;
                    }
                    $this->server->cache($context);
                    if ($builder = $this->ccl->cut($this->server)) {
                        $this->count++;
                        /**
                         * @var PDOBuild $event
                         */
                        $event = unserialize($builder);
                        try {
                            switch ($event->name) {
                                case 'pdo.proxy.query':
                                    $pdoStatement = $this->pdoConnection->prepare($event->query);
                                    foreach ($event->bindings as $key => $value) {
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
                                    foreach ($event->bindParams as $key => $value) {
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
                                    break;
                                case 'pdo.proxy.beginTransaction':
                                    $result = $this->pdoConnection->beginTransaction();
                                    break;
                                case 'pdo.proxy.commit':
                                    $result = $this->pdoConnection->commit();
                                    break;
                                case 'pdo.proxy.rollBack':
                                    $result = $this->pdoConnection->rollBack();
                                    break;
                                default :
                                    $result = false;
                            }
                            $event->data = $result;
                            $this->ccl->send($this->server, $event->serialize());
                            $this->count--;
                        } catch (Exception $exception) {
                            $pdoProxyException = new PDOProxyExceptionBuild(get_class($exception), [
                                'message' => $exception->getMessage(),
                                'code' => $exception->getCode(),
                                'file' => $exception->getFile(),
                                'line' => $exception->getLine(),
                                'trace' => null,
                                'previous' => null
                            ]);
                            $event->data = $pdoProxyException;
                            $event->serialize();
                            $this->ccl->send($this->server, $event->serialize());
                            $this->count--;
                        }
                    }
                }
            }
        }
    }
}