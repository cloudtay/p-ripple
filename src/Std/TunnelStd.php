<?php
declare(strict_types=1);

namespace Std;

/**
 * 通道标准
 */
interface TunnelStd
{
    public const EXT = '.switch';

    /**
     * CREATE CHANNEL
     * @param mixed $base
     * @return self|false
     */
    public static function create(mixed $base): self|false;

    /**
     * CONNECTION CHANNEL
     * @param string $name
     * @return false|static
     */
    public static function link(string $name): self|false;

    /**
     * READ DATA
     * @param int $length
     * @param int|null $resultLength
     * @return string|false
     */
    public function read(int $length, int|null &$resultLength): string|false;

    /**
     * WRITE DATA TO THE CHANNEL
     * @param string $context
     * @param bool $async
     * @return int|bool
     */
    public function write(string $context, bool $async = false): int|bool;

    /**
     * @return void
     */
    public function close(): void;

    /**
     * @return void
     */
    public function destroy(): void;
}
