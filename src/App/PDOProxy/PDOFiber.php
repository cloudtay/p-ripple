<?php

namespace PRipple\App\PDOProxy;

use Fiber;
use PRipple\PRipple;
use PRipple\Protocol\CCL;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\Worker;

class PDOFiber extends NetWorker
{
    public const UNIX_PATH = '/tmp/pripple_illuminate_database.proxy.sock';
    public array $queue = [];

    public function onMessage(string $context, Client $client): void
    {
        /**
         * @var  Build $event
         */
        $event = unserialize($context);
        if ($fiber = $this->queue[$event->publisher] ?? null) {
            $fiber->resume($event->data);
        }
    }

    public function splitMessage(Client $client): string|false
    {
        return $this->protocol->cut($client);
    }

    public function initialize(): void
    {
        unlink('/tmp/pripple_pdo_proxy.sock');
        parent::initialize();
    }

    public function execute(string $query, array $bindings): mixed
    {
        $hash = PRipple::instance()->uniqueHash();
        $this->queue[$hash] = Fiber::getCurrent();
        $event = Build::new('pdo.proxy.query', ['query' => $query, 'bindings' => $bindings], $hash);
        foreach ($this->getClients() as $client) {
            $ccl = new CCL;
            $ccl->send($client, $event->serialize());
            break;
        }
        return PRipple::publishSync(Build::new('suspend', null, $hash));
    }

    public static function instance(): PDOFiber|Worker
    {
        return PRipple::worker(PDOFiber::class);
    }
}
