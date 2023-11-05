<?php
declare(strict_types=1);

namespace PRipple\Tests;

use PRipple\PRipple;
use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;

include __DIR__ . '/vendor/autoload.php';

/**
 *
 */
class test_file_server extends NetWorker
{
    /**
     * execute after the service starts
     * @return void
     */
    public function initialize(): void
    {
        $filePath = '/tmp/test_file';
        if (!file_exists($filePath)) {
            $file = fopen($filePath, 'w');
            for ($i = 0; $i < 1024 * 1024 * 10; $i++) {
                fwrite($file, 'a');
            }
            fclose($file);
        }
        parent::initialize();
    }

    /**
     * heartbeat
     * @return void
     */
    public function heartbeat(): void
    {
        echo '.';
    }

    /**
     * Fired when a new connection is made
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        $client->setNoBlock();
        $filePath = '/tmp/test_file';
        echo PHP_EOL . 'test file md5:' . md5_file($filePath) . PHP_EOL;
        $file = fopen($filePath, 'r');
        while (!feof($file)) {
            $client->write(fread($file, 1024));
        }
        fclose($file);
        echo PHP_EOL . 'test file send done.' . PHP_EOL;
    }

    /**
     * Process server packets
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {

    }

    /**
     * Triggered when the connection is disconnected
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
        echo PHP_EOL . 'client is disconnected.' . PHP_EOL;
    }

    /**
     * @return void
     */
    public function destroy(): void
    {

    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

$server = test_file_server::new('test_file_server')->bind('tcp://127.0.0.1:3002', [SO_REUSEADDR => true]);
PRipple::instance()->push($server);
PRipple::instance()->launch();
