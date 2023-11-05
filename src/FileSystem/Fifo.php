<?php
declare(strict_types=1);

namespace PRipple\FileSystem;


/**
 * Pipeline
 */
class Fifo
{
    public const EXT = '.fifo';
    private mixed $stream;
    private string $name;
    private string $path;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->path = PP_RUNTIME_PATH . '/fifo_' . $name . Fifo::EXT;
        $this->stream = fopen($this->path, 'r+');
    }

    /**
     * @param string $name
     * @return Fifo|false
     */
    public static function create(string $name): Fifo|false
    {
        $path = PP_RUNTIME_PATH . '/fifo_' . $name;
        if (file_exists($path . Fifo::EXT)) {
            return false;
        } elseif (posix_mkfifo($path . Fifo::EXT, 0666)) {
            return new self($name);
        } else {
            return false;
        }
    }

    /**
     * Create a pipeline
     * @param string $name
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return file_exists(PP_RUNTIME_PATH . '/fifo_' . $name . Fifo::EXT);
    }

    /**
     * NfsService the pipes
     * @param string $name
     * @return Fifo|false
     */
    public static function link(string $name): Fifo|false
    {
        $path = PP_RUNTIME_PATH . '/fifo_' . $name;
        if (!!file_exists($path . Fifo::EXT)) {
            return new self($name);
        } else {
            return false;
        }
    }

    /**
     * Write data to the pipeline
     * @param string $context
     * @return int
     */
    public function write(string $context): int
    {
        return fwrite($this->stream, $context);
    }

    /**
     * Read a row
     * @return string
     */
    public function fgets(): string
    {
        return fgets($this->stream);
    }

    /**
     * Reads the contents of the specified length
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        return fread($this->stream, $length);
    }

    /**
     * Get the entire pipeline content
     * @return string
     */
    public function full(): string
    {
        return stream_get_contents($this->stream);
    }

    /**
     * Destroy the pipe
     * @return void
     */
    public function release(): void
    {
        $this->close();
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Close the pipe
     * @return void
     */
    public function close(): void
    {
        if (get_resource_type($this->stream) !== 'Unknown') {
            fclose($this->stream);
        }
    }

    /**
     * Set the jam mode
     * @param bool $bool
     * @return bool
     */
    public function setBlocking(bool $bool): bool
    {
        return stream_set_blocking($this->stream, $bool);
    }

    /**
     * Gets the current pipeline name
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

}
