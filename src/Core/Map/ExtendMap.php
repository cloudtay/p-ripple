<?php

namespace Core\Map;

/**
 * Class ExtendMap
 */
class ExtendMap
{
    /**
     * @var array
     */
    public static array $extendMap = [];

    /**
     * @param string $name
     * @return mixed
     */
    public static function get(string $name): mixed
    {
        return ExtendMap::$extendMap[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @return void
     */
    public static function set(string $name, mixed $value): void
    {
        ExtendMap::$extendMap[$name] = $value;
    }
}
