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

namespace Support\Http;

use Closure;
use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Core\Output;
use InvalidArgumentException;
use PRipple;
use Support\WebApplication\Extends\Session\SessionManager;
use Throwable;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Tunnel\SocketTunnelException;
use Worker\Worker;

/**
 * Http服务类
 */
class HttpWorker extends Worker
{
    /**
     * 上传文件路径
     * @var string
     */
    public static string $uploadPath;

    /**
     * Http流工厂
     * @var RequestFactory $requestFactory
     */
    private RequestFactory $requestFactory;

    /**
     * 请求处理器
     * @var Closure $requestHandler
     */
    private Closure $requestHandler;

    /**
     * 请求队列
     * @var Request[] $requests
     */
    private array $requests = [];

    /**
     * 请求异常处理器
     * @var Closure $exceptionHandler
     */
    private Closure $exceptionHandler;

    /**
     * 会话管理
     * @var SessionManager $sessionManager
     */
    private SessionManager $sessionManager;

    /**
     * 定义请求处理
     * @param Closure $requestHandler
     * @return void
     */
    public function defineRequestHandler(Closure $requestHandler): void
    {
        $this->requestHandler = $requestHandler;
    }

    /**
     * 定义异常处理器
     * @param Closure $exceptionHandler
     * @return void
     */
    public function defineExceptionHandler(Closure $exceptionHandler): void
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * 心跳
     * @return void
     */
    public function heartbeat(): void
    {
        while ($request = array_shift($this->requests)) {
            $request->setup(function () use ($request) {
                try {
                    $requesting = call_user_func($this->requestHandler, $request);
                    foreach ($requesting as $response) {
                        if ($response instanceof Response) {
                            $response->headers['Server'] = 'PRipple';
                            if ($request->keepAlive) {
                                $response->headers['Connection'] = 'Keep-Alive';
                            }
                            $request->client->send($response->__toString());
                            if ($response->isFile) {
                                $response->headers['Connection'] = 'Keep-Alive';
                                $this->queue[$request->hash]     = Build::new(
                                    Request::EVENT_DOWNLOAD,
                                    $response,
                                    $request->hash
                                );
                            } elseif (!$request->keepAlive) {
                                $this->closeClient($request->client);
                            }
                        }
                    }
                    $this->recover($request->hash);
                } catch (SocketTunnelException|FileException $exception) {
                    $this->recover($request->hash);
                } catch (Throwable $exception) {
                    $this->handleException($exception, $request);
                }
            });

            /**
             * Following the design of the Collaborative Fiber Std, the Worker exception processor is injected into the Collaborative Fiber
             * @param Throwable $exception
             * @return true
             */
            $request->exceptionHandler = function (Throwable $exception) use ($request) {
                call_user_func_array($this->exceptionHandler, [$exception, $request]);
                return true;
            };

            try {
                if ($request->execute()) {
                    CollaborativeFiberMap::$collaborativeFiberMap[$request->hash] = $request;
                } else {
                    $this->recover($request->hash);
                }
            } catch (Throwable $exception) {
                $this->handleException($exception, $request);
                $this->recover($request->hash);
            }
        }

        foreach ($this->queue as $hash => $event) {
            $response = $event->data;
            /**
             * @var Response $response
             */
            switch ($event->name) {
                case Request::EVENT_DOWNLOAD:
                    do {
                        if (!$content = $response->file->readWithTrace($response->client->getSendBufferSize())) {
                            $this->recover($hash);
                            break;
                        }
                    } while ($response->client->send($content));
                    break;
                default:
                    break;
            }
        }
        $this->busy = false;
    }

    /**
     * @param string $hash
     * @return void
     */
    private function recover(string $hash): void
    {
        CollaborativeFiberMap::getCollaborativeFiber($hash)?->destroy();
        unset($this->queue[$hash]);
    }

    /**
     * @param Throwable $exception
     * @param Request   $request
     * @return void
     */
    public function handleException(Throwable $exception, Request $request): void
    {
        if (isset($this->exceptionHandler)) {
            try {
                call_user_func($this->exceptionHandler, $exception, $request);
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        } else {
            Output::printException($exception);
        }
        $this->recover($request->hash);
    }

    /**
     * 创建请求工厂
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->subscribe(Request::EVENT_UPLOAD);
        $this->subscribe(Request::EVENT_DOWNLOAD);
        $this->requestFactory = new RequestFactory($this);
        if (!$uploadPath = PRipple::getArgument('HTTP_UPLOAD_PATH')) {
            Output::printException(new InvalidArgumentException('HTTP_UPLOAD_PATH is not defined'));
            exit(0);
        }
        HttpWorker::$uploadPath = $uploadPath;
    }

    /**
     * 设置为非堵塞模式
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
        $client->setNoBlock();
        $client->setReceiveBufferSize(81920);
        $client->setSendBufferSize(81920);
    }

    /**
     * 原始报文到达,压入请求工厂
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        try {
            if (($request = $this->requestFactory->revolve($context, $client)) instanceof Request) {
                $this->onRequest($request);
            }
        } catch (RequestSingleException $exception) {
            $client->send(
                (new Response())
                    ->setStatusCode(400)
                    ->setBody($exception->getMessage())
                    ->__toString()
            );
        }
    }

    /**
     * 一个新请求到达
     * @param Request $request
     * @return void
     */
    public function onRequest(Request $request): void
    {
        $this->requests[$request->hash] = $request;
        unset(CollaborativeFiberMap::$collaborativeFiberMap[$request->client->getName()]);
        $request->client->setName($request->hash);
        $this->busy = true;
    }

    /**
     * 回收请求
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
        $this->recover($client->getName());
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        $hash = $event->source;
        if ($collaborativeFiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            try {
                if ($collaborativeFiber->terminated()) {
                    $this->recover($hash);
                } else {
                    $this->resume($hash, $event);
                }
            } catch (Throwable $exception) {
                $collaborativeFiber->exceptionHandler($exception);
                Output::printException($exception);
            }
        }
    }

    /**
     * 不接管任何父进程请求
     * @return void
     */
    public function forking(): void
    {
        parent::forking();
        foreach ($this->requests as $request) {
            $request->destroy();
            unset($this->requests[$request->hash]);
            unset($this->queue[$request->hash]);
        }
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}
