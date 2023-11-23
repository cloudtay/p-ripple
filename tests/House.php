<?php

namespace Tests;

use recycle\Http\Request;

class House
{
    private Request $request;

    public function __construct(Request|null $request = null)
    {
        $this->request = $request;
    }

    public function getName(): string
    {
        return 'house';
    }
}
