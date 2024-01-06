<?php

namespace Tests;

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Utils\IO;
use Cclilshy\PRipple\Utils\Process;
use Cclilshy\PRipple\Worker\Built\ProcessService;
use JetBrains\PhpStorm\NoReturn;
use PHPUnit\Framework\TestCase;
use Throwable;
use function Co\async;

class TestCoroutine extends TestCase
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * @return void
     * @throws Throwable
     * @deprecated 该测试无法反映协程优劣
     */
    #[NoReturn] public function testCoroutine(): void
    {
        ob_end_clean();

        $kernel = PRipple::configure([]);

        // 异步行为内同步等待
        async(function (Coroutine $coroutine) {
            $children = async(function () {
                \Co\sleep(3);
                return 'success';
            });

            $coroutine->await($children);

            echo 'awaited: ' . $children->result . PHP_EOL;
        });

        // 异步读取文件
        async(function (Coroutine $coroutine) {
            $readFile = async(function () {
                return IO::fileGetContents(__FILE__);
            });

            $coroutine->await($readFile);

            var_dump($readFile->result);
        });


        // 延迟执行
        async(function () {
            \Co\sleep(10);
            echo 'sleep 10s' . PHP_EOL;
        });

        // 跨进程调用
        async(function (Coroutine $coroutine) {
            Process::fork(function () {
                sleep(10);
                return ['x', 'y', 'z'];
            });

            $coroutine->on(ProcessService::PROCESS_RESULT, function (Event $event) {
                var_dump($event->data);
            });
        });

        // 资源回收器
        async(function (Coroutine $coroutine) {
            $coroutine->defer(function () {
                // TODO: 所有该作用域内的事件/工作结束后发生
            });

            //TODO: ~~~
        });

        $kernel->build()->loop();
    }
}
