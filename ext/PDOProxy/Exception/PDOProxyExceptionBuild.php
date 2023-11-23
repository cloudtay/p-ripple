<?php
declare(strict_types=1);

namespace recycle\PDOProxy\Exception;

use Worker\Prop\Build;

/**
 * PDO异常容器
 */
class PDOProxyExceptionBuild extends Build
{
    public int         $code;
    public string      $message;
    public string|null $file  = null;
    public int|null    $line  = null;
    public array|null  $trace = null;
    public string|null $previous = null;

    /**
     * @param string $name
     * @param mixed  $data
     * @param string $source
     */
    public function __construct(string $name, mixed $data, string $source)
    {
        $this->code  = intval($data['code']);
        $this->message = $data['message'];
        $this->file  = $data['file'];
        $this->line  = $data['line'];
        $this->trace = $data['trace'];
        $this->previous = $data['previous'];
        parent::__construct($name, $data, $source);
    }
}
