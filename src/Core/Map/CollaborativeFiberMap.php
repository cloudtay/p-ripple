<?php
declare(strict_types=1);

namespace Core\Map;

use Fiber;
use Std\CollaborativeFiberStd;
use Throwable;

/**
 *
 */
class CollaborativeFiberMap
{
    /**
     * @var CollaborativeFiberStd[] $collaborativeFiberMap
     */
    public static array $collaborativeFiberMap = [];

    /**
     * @param CollaborativeFiberStd $collaborative
     * @return void
     */
    public static function addCollaborativeFiber(CollaborativeFiberStd $collaborative): void
    {
        CollaborativeFiberMap::$collaborativeFiberMap[spl_object_hash($collaborative->fiber)] = $collaborative;
    }

    /**
     * @param CollaborativeFiberStd $collaborative
     * @return void
     */
    public static function removeCollaborativeFiber(CollaborativeFiberStd $collaborative): void
    {
        unset(CollaborativeFiberMap::$collaborativeFiberMap[spl_object_hash($collaborative->fiber)]);
    }

    /**
     * @param string $hash
     * @return CollaborativeFiberStd
     */
    public static function getCollaborativeFiber(string $hash): CollaborativeFiberStd
    {
        return CollaborativeFiberMap::$collaborativeFiberMap[$hash];
    }

    /**
     * @return CollaborativeFiberStd|null
     */
    public static function current(): CollaborativeFiberStd|null
    {
        return CollaborativeFiberMap::$collaborativeFiberMap[spl_object_hash(Fiber::getCurrent())];
    }

    /**
     * @param string $hash
     * @param mixed $data
     * @return mixed
     * @throws Throwable
     */
    public static function resume(string $hash, mixed $data): mixed
    {
        if ($collaborativeFiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            return $collaborativeFiber->resumeFiberExecution($data);
        }
        return null;
    }
}
