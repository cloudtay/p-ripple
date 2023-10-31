<?php
declare(strict_types=1);


namespace Cclilshy\PRipple\FileSystem;

use Cclilshy\PRipple\FileSystem\Std\FileStd;


class Pipe implements FileStd
{
    private $resource;
    private int $point;
    private int $eof;
    private string $name;
    private string $path;

    /**
     * @param string $name
     * @param int $eof
     */
    private function __construct(string $name, int $eof = -1)
    {
        $this->name = $name;
        $this->path = FileStd::STP . FS . $name . FileStd::EXT;
        $this->resource = fopen($this->path, 'r+');
        $this->point = 0;
        $this->eof = $eof;
    }

    /**
     * @param ?string $name
     * @return Pipe|false
     */
    public static function create(string|null $name = null): Pipe|false
    {
        if (!Pipe::exists($name)) {
            touch(FileStd::STP . FS . $name . FileStd::EXT);
            return new Pipe($name);
        }
        return false;
    }

    /**
     * @param string|null $name
     * @return bool
     */
    public static function exists(string|null $name = null): bool
    {
        return file_exists(FileStd::STP . FS . $name . FileStd::EXT);
    }

    /**
     * @param string|null $name
     * @return Pipe|false
     */
    public static function link(string|null $name = null): Pipe|false
    {
        if (Pipe::exists($name)) {
            return new Pipe($name, filesize(PP_RUNTIME_PATH . FS . $name . '.pipe'));
        }
        return false;
    }

    /**
     * @param string $context
     * @param ?int $start
     * @return int|false
     */
    public function write(string $context, int|null $start = 0): int|false
    {
        if (strlen($context) < 1) {
            return false;
        }

        if ($start === 0) {
            $this->flush();
        }
        $this->adjustPoint($start);
        $this->eof += strlen($context) - $start;
        return fwrite($this->resource, $context);
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        $this->eof = -1;
        $this->adjustPoint(0);
        return ftruncate($this->resource, 0);
    }

    /**
     * @param int $location
     * @return void
     */
    private function adjustPoint(int $location): void
    {
        $this->point = $location;
        fseek($this->resource, $this->point);
    }

    /**
     * @param string $content
     * @return int
     */
    public function push(string $content): int
    {
        $this->adjustPoint($this->eof);
        $this->eof += strlen($content);
        fwrite($this->resource, $content);
        return $this->eof;
    }

    /**
     * @return string|false
     */
    public function read(): string|false
    {
        return $this->section(0);
    }

    /**
     * @param int $start
     * @param int $eof
     * @return string|false
     */
    public function section(int $start, int $eof = 0): string|false
    {
        if ($eof === 0) {
            $eof = $this->eof - $start;
        }

        if ($eof > $this->eof || $eof < $start) {
            return false;
        }

        $this->adjustPoint($start);
        $length = $eof - $start + 1;
        $context = '';

        while ($length > 0) {
            if ($length > 8192) {
                $context .= fread($this->resource, 8192);
                $length -= 8192;
            } else {
                $context .= fread($this->resource, $length);
                $length = 0;
            }
        }

        return $context;
    }

    /**
     * @param bool $wait
     * @return bool
     */
    public function lock(bool $wait = true): bool
    {
        if ($wait) {
            return flock($this->resource, LOCK_EX);
        } else {
            return flock($this->resource, LOCK_EX | LOCK_NB);
        }
    }

    /**
     * @return bool
     */
    public function unlock(): bool
    {
        return flock($this->resource, LOCK_UN);
    }

    public function clone(): Pipe
    {
        return new self($this->name, $this->eof);
    }

    /**
     * @return void
     */
    public function close(): void
    {
        fclose($this->resource);
    }

    /**
     * @return void
     */
    public function release(): void
    {
        if (!get_resource_type($this->resource) == 'Unknown') {
            fclose($this->resource);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
