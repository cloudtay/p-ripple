<?php

namespace Worker\Built\JsonRpc\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Rpc
{
    public function __construct(
        public string      $name,
        public string|null $description = null
    )
    {
    }
}
