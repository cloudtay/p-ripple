<?php

namespace Cclilshy\PRipple\App\Redis;

use Cclilshy\PRipple\Build;
use Cclilshy\PRipple\Std\TaskStd;
use Fiber;

class RedisTask extends TaskStd
{

    public string $command;
    public Fiber $caller;

    public function __construct(string $command, Fiber $caller)
    {
        $this->command = $command;
        $this->caller = $caller;
        parent::__construct();
    }

    protected function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }
}
