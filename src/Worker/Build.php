<?php
declare(strict_types=1);

namespace Worker;

/**
 * 事件包实体
 */
class Build
{
    /**
     * @var string $name
     */
    public string $name;

    /**
     * @var mixed $data
     */
    public mixed $data;

    /**
     * @var mixed $source
     */
    public mixed $source;

    /**
     * @param string $name
     * @param mixed $data
     * @param mixed $source
     */
    public function __construct(string $name, mixed $data, mixed $source)
    {
        $this->name = $name;
        $this->data = $data;
        $this->source = $source;
    }

    /**
     * @param string $name
     * @param mixed $data
     * @param mixed $source
     * @return Build
     */
    public static function new(string $name, mixed $data, mixed $source): static
    {
        return new static($name, $data, $source);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->serialize();
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }
}
