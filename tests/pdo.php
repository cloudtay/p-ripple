<?php

use PRipple\App\Facade\PDOProxy;
use PRipple\App\Facade\Timer;
use PRipple\App\PDOProxy\PDOProxyWorker;
use PRipple\App\PDOProxy\PDOTransaction;
use PRipple\PRipple;
use function Cclilshy\PRipple\async;

include __DIR__ . '/vendor/autoload.php';

$pRipple = PRipple::instance();
$pdoProxyManager = PDOProxyWorker::new(PDOProxyWorker::class);

Timer::loop(1, function () {
    echo 'is running' . PHP_EOL;
});

async(function () use ($pdoProxyManager) {
    Timer::sleep(3);
    PDOProxy::transaction(function (PDOTransaction $PDOTransaction) {
        var_dump($PDOTransaction->query('select * from example where id = ?', [1], [PDO::PARAM_INT]));
        var_dump($PDOTransaction->query('update example set value = "bb" where id = ?', [1], [PDO::PARAM_INT]));
        var_dump($PDOTransaction->query('select * from example where id = ?', [1], [PDO::PARAM_INT]));
        $PDOTransaction->rollBack();
    });

    PDOProxy::query('select * from example where id = ?', [1], [PDO::PARAM_INT]);
});

async(function () use ($pdoProxyManager) {
    $options = [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $pdoProxyManager->addProxy(2, [
        'dns' => 'mysql:host=127.0.0.1;dbname=test',
        'username' => 'root',
        'password' => '123456',
        'options' => $options
    ]);
});

$pRipple->push($pdoProxyManager)->launch();
