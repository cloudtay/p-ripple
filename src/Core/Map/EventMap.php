<?php
declare(strict_types=1);

namespace Core\Map;

use Worker\Build;

/**
 *
 */
class EventMap
{
    /**
     * @var Build[] $eventMap
     */
    public static array $eventMap = [];
    public static int   $count    = 0;

    /**
     * 异步发布事件
     * @param Build $event
     * @return void
     */
    public static function push(Build $event): void
    {
        EventMap::$eventMap[] = $event;
        EventMap::$count++;
    }
}
