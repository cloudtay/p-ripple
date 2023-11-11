<?php
declare(strict_types=1);

namespace FileSystem;


/**
 * File
 */
class File
{
    /**
     * @var string
     */
    public const EXT = '.tmp';

    /**
     * @var mixed|false|resource
     */
    public mixed $file;

    /**
     * @var int
     */
    private int $point = 0;

    /**
     * @var string
     */
    public string $path;

    /**
     * @param string $path
     * @param string $mode
     */
    public function __construct(string $path, string $mode)
    {
        $this->path = $path;
        $this->file = fopen($path, $mode);
    }

    /**
     * Create a file
     * @param string $path File path
     * @param string $mode Open mode
     * @return self|false
     */
    public static function create(string $path, string $mode): self|false
    {
        if (File::exists($path)) {
            return false;
        } else {
            touch($path, 0666);
            return new self($path, $mode);
        }
    }

    /**
     * Whether the file exists
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Open the file
     * @param string $path
     * @param string $mode
     * @return false|static
     */
    public static function open(string $path, string $mode): self|false
    {
        if (File::exists($path)) {
            return new self($path, $mode);
        } else {
            return false;
        }
    }

    /**
     * Read and follow the pointer
     * @param int $length
     * @return string|false
     */
    public function readWithTrace(int $length): string|false
    {
        $content = $this->read($this->point, $length);
        $this->adjustPoint($this->point + $length);
        return $content;
    }

    /**
     * Reads content of the specified length starting at the specified position
     * @param int $start
     * @param int $length
     * @return string|false
     */
    public function read(int $start, int $length): string|false
    {
        $this->adjustPoint($start);
        return fread($this->file, $length);
    }

    /**
     * Adjusts the pointer to the specified position
     * @param int $location
     * @param int|null $whence
     * @return int
     */
    public function adjustPoint(int $location, int|null $whence = SEEK_SET): int
    {
        $this->point = $location;
        $s = fseek($this->file, $location, $whence);
        ftell($this->file);
        return $s;
    }

    /**
     * Contents of the photocopying specification
     * @param string $context Specification details
     * @return int|false
     */
    public function write(string $context): int|false
    {
        return fwrite($this->file, $context);
    }

    /**
     * Empty the file
     * @return bool
     */
    public function flush(): bool
    {
        $this->adjustPoint(0);
        return ftruncate($this->file, 0);
    }

    /**
     * Gets the file pointer
     * @return int|bool
     */
    public function getPoint(): int|bool
    {
        return ftell($this->file);
    }

    /**
     * 销毁管道
     * @return void
     */
    public function destroy(): void
    {
        $this->close();
        unlink($this->path);
    }

    /**
     * 关闭连接
     * @return void
     */
    public function close(): void
    {
        fclose($this->file);
    }
}
