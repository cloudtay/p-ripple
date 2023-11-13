<?php
declare(strict_types=1);

namespace App\PDOProxy\Exception;

use Exception;
use Throwable;

/**
 * PDO异常
 */
class PDOProxyException extends Exception
{
    private string $source;

    /**
     * @param string|null    $message
     * @param int|null       $code
     * @param Throwable|null $previous
     * @param string|null    $source
     */
    public function __construct(string|null $message = "", int|null $code = 0, Throwable|null $previous = null, string|null $source = '')
    {
        parent::__construct($message, $code, $previous);
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getsource(): string
    {
        return $this->source;
    }
}
