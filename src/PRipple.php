<?php
declare(strict_types=1);

use Core\Kernel;
use Worker\Worker;

/**
 * PRipple
 */
class PRipple
{
    private static Kernel $kernel;
    private static int    $index              = 0;
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
        define('PP_MAX_FILE_HANDLE', 10240);
        PRipple::$kernel = new Kernel();
        return PRipple::$kernel;
    }

    /**
     * 获取装配参数
     * @param string      $name
     * @param string|null $default
     * @return mixed
     */
    public static function getArgument(string $name, mixed $default = null): mixed
    {
        if ($value = PRipple::$configureArguments[$name] ?? null) {
            return $value;
        } elseif ($default) {
            return $default;
        }
        return null;
    }

    /**
     * 唯一HASH
     * @return string
     */
    public static function uniqueHash(): string
    {
        return md5(strval(PRipple::$index++));
    }

    /**
     * @param Worker $worker
     * @return Kernel
     */
    public static function pushWorker(Worker $worker): Kernel
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

    /**
     * 获取客户端ID
     * @return int
     */
    public static function getClientId(): int
    {
        return posix_getpid();
    }
}
