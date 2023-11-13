<?php
declare(strict_types=1);

namespace Tests;

use FileSystem\FileException;
use PRipple;
use Worker\NetWorker\Client;
use Worker\NetworkWorkerBase;

include __DIR__ . '/vendor/autoload.php';

/**
 *
 */
class test_file_server extends NetworkWorkerBase
{
    /**
     * heartbeat
     * @return void
     */
    public function heartbeat(): void
    {
        echo '.';
    }

    /**
     * @return void
     */
    public function destroy(): void
    {

    }

    /**
     * execute after the service starts
     * @return void
     */
    protected function initialize(): void
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
     * Fired when a new connection is made
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        $filePath = '/tmp/test_file';
        $file = fopen($filePath, 'r');
        while (!feof($file)) {
            try {
                $client->write(fread($file, 1024));
            } catch (FileException $e) {
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
    protected function onMessage(string $context, Client $client): void
    {

    }

    /**
     * Triggered when the connection is disconnected
     * @param Client $client
     * @return void
     */
    protected function onClose(Client $client): void
    {
        echo PHP_EOL . 'client is disconnected.' . PHP_EOL;
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

$kernel = PRipple::configure([
    'RUNTIME_PATH' => __DIR__,
    'HTTP_UPLOAD_PATH' => __DIR__,
]);

$server = test_file_server::new('test_file_server')->bind('tcp://127.0.0.1:3002', [SO_REUSEADDR => true]);

PRipple::kernel()->push($server);
PRipple::kernel()->launch();
