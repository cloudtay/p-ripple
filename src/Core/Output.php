<?php
declare(strict_types=1);

namespace Core;

use Throwable;

/**
 *
 */
class Output
{
    /**
     * @param Throwable $exception
     * @return void
     */
    public static function printException(Throwable $exception): void
    {
        echo "\033[1;31mException: " . get_class($exception) . "\033[0m\n";
        echo "\033[1;33mMessage: " . $exception->getMessage() . "\033[0m\n";
        echo "\033[1;34mFile: " . $exception->getFile() . "\033[0m\n";
        echo "\033[1;34mLine: " . $exception->getLine() . "\033[0m\n";
        echo "\033[0;32mStack trace:\033[0m\n";
        $trace = $exception->getTraceAsString();
        $traceLines = explode("\n", $trace);
        foreach ($traceLines as $line) {
            echo "\033[0;32m" . $line . "\033[0m\n";
        }
        echo PHP_EOL;
    }

    /**
     * @param string $title
     * @param string ...$contents
     * @return void
     */
    public static function info(string $title, string ...$contents): void
    {
        echo "\033[1;32m" . $title . "\033[0m";
        foreach ($contents as $content) {
            echo "\033[1;33m" . $content . "\033[0m";
        }
        echo PHP_EOL;
    }
}
