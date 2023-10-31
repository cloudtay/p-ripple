<?php

namespace Cclilshy\PRipple;

class Build
{
    public string $name;
    public mixed $data;
    public string $publisher;

    public function __construct(string $name, mixed $data, string $publisher)
    {
        $this->name = $name;
        $this->data = $data;
        $this->publisher = $publisher;
    }

    public static function new(string $name, mixed $data, string $publisher): Build
    {
        return new Build($name, $data, $publisher);
    }
}
