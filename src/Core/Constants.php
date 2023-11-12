<?php
declare(strict_types=1);

namespace Core;

/**
 *
 */
class Constants
{
    public const        VERSION                  = '0.1.1';
    public const        EVENT_SUSPEND            = 'suspend';
    public const        EVENT_SOCKET_EXPECT      = 'socket.expect';
    public const        EVENT_SOCKET_READ        = 'socket.read';
    public const        EVENT_SOCKET_WRITE       = 'socket.write';
    public const        EVENT_SOCKET_SUBSCRIBE   = 'socket.subscribe';
    public const        EVENT_SOCKET_UNSUBSCRIBE = 'socket.unsubscribe';
    public const        EVENT_EVENT_SUBSCRIBE    = 'event.subscribe';
    public const        EVENT_EVENT_UNSUBSCRIBE  = 'event.unsubscribe';
    public const        EVENT_HEARTBEAT          = 'heartbeat';
    public const        EVENT_TEMP_FIBER         = 'temp.fiber';
    public const        EVENT_KERNEL_RATE_SET    = 'kernel.rate.set';
    public const        EVENT_SOCKET_BUFFER_UN   = 'socket.buffer.on';
    public const        EVENT_SOCKET_BUFFER      = 'socket.buffer';
}
