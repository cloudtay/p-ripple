<?php
declare(strict_types=1);

namespace Worker;

/**
 * 事件构建器
 */
class Build
{
    public string $name;
    public mixed $data;
    public mixed $publisher;

    /**
     * @param string $name
     * @param mixed $data
     * @param mixed $publisher
     */
    public function __construct(string $name, mixed $data, mixed $publisher)
    {
        $this->name = $name;
        $this->data = $data;
        $this->publisher = $publisher;
    }

    /**
     * @param string $name
     * @param mixed $data
     * @param mixed $publisher
     * @return Build
     */
    public static function new(string $name, mixed $data, mixed $publisher): static
    {
        return new static($name, $data, $publisher);
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
