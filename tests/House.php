<?php

namespace Tests;

use App\Http\Request;
use Std\CollaborativeFiberStd;
use Std\DependencyInjectionStandard;

class House implements DependencyInjectionStandard
{
    private Request $request;

    public function __construct(Request|null $request = null)
    {
        $this->request = $request;
    }

    public static function createInjector(CollaborativeFiberStd $collaborativeFiberStd): static
    {
        if ($collaborativeFiberStd instanceof Request) {
            return new static($collaborativeFiberStd);
        }
        return new static();
    }

    public function getPath(): string
    {
        return $this->request->path;
    }
}
