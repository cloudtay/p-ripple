<?php

namespace Core\Map;

use Socket;

/**
 * Stream型Socket映射
 */
class StreamMap
{
    /**
     * @var resource[] $streams
     */
    public static array $streams = [];

    /**
     * @var resource[] $streamHashMap
     */
    public static array $streamHashMap = [];

    /**
     * @var int
     */
    public static int $count = 0;

    /**
     * @param mixed $stream
     * @return int
     */
    public static function addStreamSocket(mixed $stream): int
    {
        $streamId = intval($stream);
        $socket   = socket_import_stream($stream);
        $hash     = spl_object_hash($socket);
        SocketMap::addSocket($socket, $streamId);
        StreamMap::$streamHashMap[$hash] = $stream;
        StreamMap::$streams[$streamId]   = $stream;
        StreamMap::$count++;
        return $streamId;
    }

    /**
     * @param mixed $stream
     * @return int
     */
    public static function removeStreamSocket(mixed $stream): int
    {
        $streamId = intval($stream);
        if ($socketHash = SocketMap::removeSocketByStreamId($streamId)) {
            unset(StreamMap::$streamHashMap[$socketHash]);
        }
        unset(StreamMap::$streams[$streamId]);
        StreamMap::$count--;
        return $streamId;
    }

    /**
     * @param Socket $socket
     * @return resource|null
     */
    public static function getStreamBySocket(Socket $socket)
    {
        return StreamMap::getStreamByHash(spl_object_hash($socket));
    }

    /**
     * @param string $hash
     * @return resource|null
     */
    public static function getStreamByHash(string $hash)
    {
        return StreamMap::$streamHashMap[$hash] ?? null;
    }
}
