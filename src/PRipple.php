<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

declare(strict_types=1);

use Core\Kernel;

/**
 * PRipple
 */
class PRipple
{
    private static Kernel $kernel;
    private static int    $index              = 0;
    private static array  $configureArguments = [];
    private static bool   $isConsole;

    /**
     * 初次装配内核
     * @param array $arguments
     * @return Kernel
     */
    public static function configure(array $arguments): Kernel
    {
        PRipple::$isConsole          = PHP_SAPI === 'cli';
        PRipple::$configureArguments = $arguments;
        PRipple::initEnvConfig();
        PRipple::initConstant();
        PRipple::$kernel = new Kernel();
        return PRipple::$kernel;
    }

    /**
     * 获取装配参数
     * @param string|null $name
     * @param string|null $default
     * @return mixed
     */
    public static function getArgument(string|null $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return PRipple::$configureArguments;
        }
        if ($value = PRipple::$configureArguments[$name] ?? null) {
            return $value;
        } elseif ($default) {
            return $default;
        }
        return null;
    }

    /**
     * 唯一HASH(当前进程安全)
     * @return string
     */
    public static function uniqueHash(): string
    {
        return md5(strval(PRipple::uniqueId()));
    }

    /**
     * 唯一ID(当前进程安全)
     * @return int
     */
    public static function uniqueId(): int
    {
        return PRipple::$index++;
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
     * 初始化环境配置
     */
    private static function initEnvConfig(): void
    {
        error_reporting(E_ALL & ~E_WARNING);
        ini_set('max_execution_time', 0);
    }

    /**
     * 初始化常量
     * @return void
     */
    private static function initConstant(): void
    {
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PP_START_TIMESTAMP', time());
        define('PP_ROOT_PATH', __DIR__);
        define('PP_RUNTIME_PATH', PRipple::getArgument('PP_RUNTIME_PATH', '/tmp'));
        define('PP_MAX_FILE_HANDLE', 10240);
    }

    /**
     * @param int|string $key
     * @param mixed      $value
     * @return void
     */
    public static function config(int|string $key, mixed $value): void
    {
        PRipple::$configureArguments[$key] = $value;
    }

    /**
     * @return bool
     */
    public static function isConsole(): bool
    {
        return PRipple::$isConsole;
    }
}
