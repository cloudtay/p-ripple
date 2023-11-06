<?php

namespace PRipple\App\PDOProxy\Exception;

use PRipple\Worker\Build;

class PDOProxyExceptionBuild extends Build
{
    public int $code;
    public string $message;
    public string|null $file = null;
    public int|null $line = null;
    public array|null $trace = null;
    public string|null $previous = null;

    public function __construct(string $name, mixed $data)
    {
        $this->code = intval($data['code']);
        $this->message = $data['message'];
        $this->file = $data['file'];
        $this->line = $data['line'];
        $this->trace = $data['trace'];
        $this->previous = $data['previous'];
        parent::__construct($name, $data, PDOProxyExceptionBuild::class);
    }
}
