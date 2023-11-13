<?php
declare(strict_types=1);

use Core\Kernel;
use Core\Output;
use Worker\WorkerBase;

/**
 * PRipple
 */
class PRipple
{
    private static Kernel $kernel;
    private static int $index = 0;
    private static array  $configureArguments = [];

    /**
     * 安装
     * @param array $arguments
     * @return Kernel
     */
    public static function configure(array $arguments): Kernel
    {
        PRipple::$configureArguments = $arguments;
        error_reporting(E_ALL & ~E_WARNING);
        ini_set('max_execution_time', 0);
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PP_START_TIMESTAMP', time());
        define('PP_ROOT_PATH', __DIR__);
        define('PP_RUNTIME_PATH', PRipple::getArgument('RUNTIME_PATH', '/tmp'));
        define('PP_MAX_FILE_HANDLE', intval(shell_exec("ulimit -n")));
        PRipple::$kernel = new Kernel();
        return PRipple::$kernel;
    }

    /**
     * 获取装配参数
     * @param string $name
     * @param string|null $default
     * @return mixed
     */
    public static function getArgument(string $name, string|null $default = null): mixed
    {
        if ($value = PRipple::$configureArguments[$name] ?? null) {
            return $value;
        } elseif ($default) {
            return $default;
        }

        try {
            throw new Exception("Argument {$name} not found");
        } catch (Exception $exception) {
            Output::printException($exception);
            exit;
        }

    }

    /**
     * 唯一HASH
     * @return string
     */
    public static function uniqueHash(): string
    {
        return md5(strval(PRipple::$index++));
    }

    public static function pushWorker(WorkerBase $worker): Kernel
    {
        PRipple::kernel()->push($worker);
        return PRipple::kernel();
    }

    /**
     * 获取内核
     * @return Kernel
     */
    public static function kernel(): Kernel
    {
        return PRipple::$kernel;
    }
}
