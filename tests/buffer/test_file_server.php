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

namespace Tests\http\buffer;

use Core\FileSystem\FileException;
use Core\Output;
use PRipple;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

include __DIR__ . '/../vendor/autoload.php';

/**
 *
 */
class test_file_server extends Worker
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
     * Fired when a new connection is made
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
        $filePath = '/tmp/test_file';
        $file     = fopen($filePath, 'r');
        while (!feof($file)) {
            try {
                $client->write(fread($file, 1024));
            } catch (FileException $exception) {
                Output::printException($exception);
            }
        }
        fclose($file);
        echo PHP_EOL . 'test file md5:' . md5_file($filePath) . PHP_EOL;
        echo 'test file size: ' . filesize($filePath) . PHP_EOL;
        echo PHP_EOL . 'test file send done.' . PHP_EOL;
    }


    /**
     * Triggered when the connection is disconnected
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
        echo PHP_EOL . 'client is disconnected.' . PHP_EOL;
    }

    public function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }

    public function onMessage(string $context, TCPConnection $client): void
    {
        // TODO: Implement onMessage() method.
    }

    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }
}

$kernel = PRipple::configure([
    'RUNTIME_PATH'     => '/tmp',
    'HTTP_UPLOAD_PATH' => '/tmp',
]);

$server = test_file_server::new('test_file_server')->bind('tcp://127.0.0.1:3002', [SO_REUSEADDR => true]);
PRipple::kernel()->push($server)->launch();

