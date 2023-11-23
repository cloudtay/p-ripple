<?php

namespace Core\Std;


use JetBrains\PhpStorm\NoReturn;
use Socket;

/**
 * WorkerInterface
 */
interface WorkerInterface
{
    /**
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void;

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void;

    /**
     * 处理套接字
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void;

    /**
     * 处理异常套接字
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void;

    /**
     * 心跳
     * @return void
     */
    public function heartbeat(): void;

    /**
     * 释放资源
     * @return void
     */
    public function destroy(): void;
}
