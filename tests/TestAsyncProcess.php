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

/**
 * 测试异步进程
 */
class TestAsyncProcess extends TestCase
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
     * 获取子进程返回
     * @return void
     */
    #[NoReturn] public function testAsyncProcess(): void
    {
        ob_end_flush();
        async(function (Coroutine $coroutine) {
            process(function () {
                sleep(3);
                return ['a', 'b', 'c'];
            });

            $coroutine->on(ProcessService::PROCESS_RESULT, function (Coroutine $coroutine, Event $event) {
                var_dump($event->data);
            });

            $coroutine->on(ProcessService::PROCESS_EXCEPTION, function () {
            });
            return true;
        });
        $this->launch();
    }

    /**
     * @return void
     */
    #[NoReturn] private function launch(): void
    {
        PRipple::kernel()->build()->loop();
    }
}
