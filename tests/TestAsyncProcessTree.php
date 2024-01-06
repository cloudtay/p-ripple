<?php

namespace Tests;

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Worker\Built\ProcessService;
use JetBrains\PhpStorm\NoReturn;
use PHPUnit\Framework\TestCase;
use function Co\async;
use function Co\process;
use function Co\sleep;

/**
 * 测试异步进程稳定
 */
class TestAsyncProcessTree extends TestCase
{
    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
        PRipple::configure(['PP_RUNTIME_PATH', '/tmp']);
    }

    /**
     * 子进程中获取子进程返回
     * @return void
     */
    #[NoReturn] public function testAsyncProcessTree(): void
    {
        ob_end_flush();
        async(function (Coroutine $coroutine) {
            sleep(1);
            process(function () {
                $this->asyncProcess();
            });
        });
        $this->launch();
    }

    /**
     * 获取子进程返回
     * @return void
     */
    #[NoReturn] private function asyncProcess(): void
    {
        async(function (Coroutine $coroutine) {
            process(function () {
                return ['a', 'b', 'c'];
            });

            $coroutine->on(ProcessService::PROCESS_RESULT, function (mixed $result, Event $event) {
                var_dump($result);
            });
        });
    }

    /**
     * @return void
     */
    #[NoReturn] private function launch(): void
    {
        PRipple::kernel()->build()->loop();
    }
}
