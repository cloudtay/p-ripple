<?php

namespace Tests;

use Cclilshy\PRipple\PRipple;
use JetBrains\PhpStorm\NoReturn;
use PHPUnit\Framework\TestCase;
use Co;

/**
 * @Class TestMemory
 * 内存调试过程请使用Xdebug扩展
 */
class TestMemory extends TestCase
{
    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    #[NoReturn] public function testMemory(): void
    {
        ob_end_flush();
        $kernel = PRipple::configure([]);

        $kernel->build()->loop();
    }
}
