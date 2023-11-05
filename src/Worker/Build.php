<?php
declare(strict_types=1);

namespace PRipple\Worker;

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
    public static function new(string $name, mixed $data, mixed $publisher): Build
    {
        return new Build($name, $data, $publisher);
    }

    public function __toString(): string
    {
        return $this->serialize();
    }

    public function serialize(): string
    {
        return serialize($this);
    }
}
