<?php
declare(strict_types=1);

namespace Core\Map;

use Socket;

/**
 *
 */
class SocketMap
{
    /**
     * @var Socket[] $sockets
     */
    public static array $sockets = [];

    /**
     * @var array $socketHashMap
     */
    public static array $workerMap = [];

    /**
     * @var int
     */
    public static int $count = 0;
}
