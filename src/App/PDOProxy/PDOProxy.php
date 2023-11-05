<?php

namespace PRipple\App\PDOProxy;

use Exception;
use PDO;
use PRipple\PRipple;
use PRipple\Protocol\CCL;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\NetWorker\SocketType\SocketUnix;

class PDOProxy
{
    public static function launch(string $dns, string $username, string $password, array $options): void
    {
        $pdo = new PDO($dns, $username, $password, $options);
        try {
            if (!$proxy = SocketUnix::connect('/tmp/pripple_illuminate_database.proxy.sock')) {
                exit;
            }
            $tunnel = new Client($proxy, SocketUnix::class);
            $ccl = new CCL;
            while (true) {
                if ($builder = $ccl->cut($tunnel)) {
                    /**
                     * @var Build $event
                     */
                    $event = unserialize($builder);
                    $query = $event->data['query'];
                    $bindings = $event->data['bindings'];
                    $pdoStatement = $pdo->prepare($query);
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
                    if ($pdoStatement->execute()) {
                        $result = $pdoStatement->fetchAll();
                    } else {
                        $result = false;
                    }
                    $event->data = $result;
                    $ccl->send($tunnel, $event->serialize());
                } else {
                    exit;
                }
            }
        } catch (Exception $exception) {
            PRipple::printExpect($exception);
        }
    }
}
