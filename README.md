<p align="center">
<img src="https://www.cloudtay.com/static/image/logo-wide.png" width="420" alt="Logo">
</p>
<p align="center">
<a href="#"><img src="https://img.shields.io/badge/PHP-%3E%3D%208.3-blue" alt="Build Status"></a>
<a href="https://packagist.org/packages/cclilshy/p-ripple"><img src="https://img.shields.io/packagist/dt/cclilshy/p-ripple" alt="Download statistics"></a>
<a href="https://packagist.org/packages/cclilshy/p-ripple"><img src="https://img.shields.io/packagist/v/cclilshy/p-ripple" alt="Stable version"></a>
<a href="https://packagist.org/packages/cclilshy/p-ripple"><img src="https://img.shields.io/packagist/l/cclilshy/p-ripple" alt="License"></a>
</p>
<p>
PRipple is a high-performance native PHP coroutine framework designed to solve PHP's challenges in high-concurrency, complex network communication and data manipulation.
This framework uses an innovative architecture and an efficient programming model to provide a robust and flexible back-end support for modern web and web applications.
By using PRipple,
you will experience the advantages
of managing tasks from a global view of the system and efficiently handling network traffic and data.
</p>
<p align="center">
    <a target="_blank" href="https://cloudtay.github.io/p-ripple-document/"><strong>Document »</strong></a>
    ·
    <a target="_blank" href="https://cc.cloudtay.com/">Blog's</a>
    ·
    <a target="_blank" href="mailto:cclilshy@163.com">Contact Email</a>
</p>

### Extend dependencies

#### Standard dependency

<a href="https://www.php.net/manual/en/book.sockets.php" target="_blank"><img src="https://img.shields.io/badge/Extension-socket-brightgreen" alt="PHP extension"></a> <a href="https://www.php.net/manual/en/book.pcntl.php" target="_blank"><img src="https://img.shields.io/badge/Extension-pcntl-blue" alt="PHP extension"></a> <a href="https://www.php.net/manual/en/book.posix.php" target="_blank"><img src="https://img.shields.io/badge/Extension-posix-blueviolet" alt="PHP extension"></a>

#### Enhanced dependencies

<a href="https://www.php.net/manual/en/book.event.php" target="_blank"><img src="https://img.shields.io/badge/Extension-event-lightgrey" alt="PHP extension"></a> <a href="https://www.php.net/manual/en/book.redis.php" target="_blank"><img src="https://img.shields.io/badge/Extension-redis-lightgrey" alt="PHP extension"></a>

## working principle

The working principle of services in the PRipple system is based on event-driven and task coordination. Each service has
its own listening network and events in the system.
It also maintains a task queue. These services are in a dormant state. When the program is started, they will start
working from the first heartbeat event received.
The service will drive task generation based on the network, events and heartbeat signals it subscribes to. Services
effectively utilize CPU resources by creating coroutines,
Allows multiple tasks to be performed simultaneously without interfering with each other.

## Contact

Email : cclilshy@163.com

Blogs : [https://cc.cloudtay.com/](https://cc.cloudtay.com/)

## Acknowledgments

Laravel: [https://laravel.com/](https://laravel.com/)

Jetbrains: [https://www.jetbrains.com/](https://www.jetbrains.com/)

Symfony: [https://symfony.com/](https://symfony.com/)

PHP: [https://www.php.net/](https://www.php.net/)
