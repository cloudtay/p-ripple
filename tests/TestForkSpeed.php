<?php

namespace Tests;

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Worker\Built\ProcessService;
use Co;
use JetBrains\PhpStorm\NoReturn;
use PHPUnit\Framework\TestCase;
use function Co\process;

class TestForkSpeed extends TestCase
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    #[NoReturn] public function testForkSpeed(): void
    {
        ob_end_clean();
        $kernel = PRipple::configure([]);
        Co\async(function (Coroutine $coroutine) {
            for ($i = 0; $i < 100; $i++) {
                process(function () {
                    return posix_getpid();
                });
            }
            $coroutine->on(ProcessService::PROCESS_RESULT, function (mixed $result, Event $event) {
                var_dump($event->source . ':' . $result);
            });
        });
        $kernel->build()->loop();
    }
}
