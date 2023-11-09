<?php
declare(strict_types=1);

namespace Tests;

use FileSystem\FileException;
use PRipple;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketAisleException;
use Worker\NetworkWorkerInterface;

include __DIR__ . '/vendor/autoload.php';

/**
 *
 */
class test_file_server extends NetworkWorkerInterface
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
        $filePath = '/tmp/test_file';
        $file = fopen($filePath, 'r');
        while (!feof($file)) {
            try {
                $client->write(fread($file, 1024));
            } catch (FileException|SocketAisleException $e) {
            }
        }
        fclose($file);

//        PRipple::worker(BufferWorker::class)->heartbeat();

        echo PHP_EOL . 'test file md5:' . md5_file($filePath) . PHP_EOL;
        echo 'test file size: ' . filesize($filePath) . PHP_EOL;
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
    public function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

$kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$server = test_file_server::new('test_file_server')->bind('tcp://127.0.0.1:3002', [SO_REUSEADDR => true]);

PRipple::instance()->push($server);
PRipple::instance()->launch();
