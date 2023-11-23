<?php
declare(strict_types=1);

namespace Core\FileSystem\Std;


/**
 * 文件标准
 */
interface FileStd
{
    public const STP = PP_RUNTIME_PATH;
    public const EXT = '.pipe';

    /**
     * @param string|null $name
     * @return false|static
     */
    public static function create(string|null $name = null): self|false;

    /**
     * @param string|null $name
     * @return false|static
     */
    public static function link(string|null $name = null): self|false;

    /**
     * @param string|null $name
     * @return bool
     */
    public static function exists(string|null $name = null): bool;

    /**
     * @param string   $context
     * @param int|null $start
     * @return int|false
     */
    public function write(string $context, int|null $start = 0): int|false;

    /**
     * @param int $start
     * @param int $eof
     * @return string|false
     */
    public function section(int $start, int $eof): string|false;

    /**
     * @return bool
     */
    public function flush(): bool;

    /**
     * @return string|false
     */
    public function read(): string|false;

    /**
     * @return void
     */
    public function close(): void;

    /**
     * @return void
     */
    public function release(): void;

}
