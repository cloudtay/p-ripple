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

namespace Worker\Built\JsonRpc;

use Core\FileSystem\FileException;
use Core\Output;
use Protocol\Slice;
use Throwable;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

class JsonRpcServer extends Worker
{
    public Worker $worker;

    /**
     * 加载
     * @param Worker $worker
     * @return Worker
     */
    public static function load(Worker $worker): Worker
    {
        return new JsonRpcServer($worker);
    }

    /**
     * JsonRpcServer constructor.
     * @param Worker $worker
     */
    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
        parent::__construct("{$this->worker->name}.rpc");
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->protocol(Slice::class)->bind($this->worker->getRpcServiceAddress());
    }

    /**
     * RPC客户端消息到达
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        if ($info = json_decode($context)) {
            if (isset($info->method)) {
                $info->params[] = $client;
                try {
                    if (method_exists($this->worker, $info->method)) {
                        $result = call_user_func_array([$this->worker, $info->method], $info->params);
                    } elseif (function_exists($info->method)) {
                        $result = call_user_func_array($info->method, $info->params);
                    } else {
                        $result = null;
                    }
                    try {
                        $this->slice->send($client, json_encode([
                            'version' => '2.0',
                            'code'    => 0,
                            'result'  => $result,
                            'id'      => $info->id,
                        ]));
                    } catch (FileException $exception) {
                        Output::printException($exception);
                    }
                } catch (Throwable $exception) {
                    try {
                        $this->slice->send($client, json_encode([
                            'version' => '2.0',
                            'error'   => [
                                'code'    => -32603,
                                'message' => $exception->getMessage(),
                                'data'    => $exception->getTraceAsString(),
                            ],
                            'id'      => $info->id,
                        ]));
                    } catch (FileException $exception) {
                        Output::printException($exception);
                    }
                }
            }
        }
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
        socket_set_option($client->getSocket(), SOL_SOCKET, SO_KEEPALIVE, 1);
    }

    public function onClose(TCPConnection $client): void
    {
        // TODO: Implement onClose() method.
    }

    public function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }

    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }
}
