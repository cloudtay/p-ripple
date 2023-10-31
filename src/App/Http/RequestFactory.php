<?php

namespace Cclilshy\PRipple\App\Http;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Worker\NetWorker;
use Closure;

class RequestFactory
{
    public const INVALID = -1;
    public const COMPLETE = 2;
    public const INCOMPLETE = 1;

    /**
     * @var Closure $observer
     */
    private Closure $observer;

    /**
     * @var NetWorker $httpService
     */
    private NetWorker $httpService;

    /**
     * @var RequestSingle[] $singles
     */
    private array $singles = [];

    /**
     * @var RequestSingle[] $transfers
     */
    private array $transfers = [];

    public function __construct(Closure $observer, NetWorker $httpService)
    {
        $this->observer = $observer;
        $this->httpService = $httpService;
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     * @throws RequestSingleException
     */
    public function revolve(string $context, Client $client): void
    {
        if ($single = $this->transfers[$client->getHash()] ?? null) {
            $single->revolve($context);
            return;
        } elseif (!$single = $this->singles[$client->getHash()] ?? null) {
            $this->singles[$client->getHash()] = $single = new RequestSingle($client);
        }
        $single->revolve($context);
        if (isset($single->method) && $single->method === 'POST' && $single->upload) {
            call_user_func($this->observer, $single->build());
            $this->transfers[$client->getHash()] = $single;
            unset($this->singles[$client->getHash()]);
        }
        switch ($single->statusCode) {
            case RequestFactory::COMPLETE:
                call_user_func($this->observer, $single->build());
                unset($this->singles[$client->getHash()]);
                break;
            case RequestFactory::INVALID:
                unset($this->singles[$client->getHash()]);
                $this->httpService->removeClient($client);
                break;
            case RequestFactory::INCOMPLETE:
                break;
        }
    }

    public function recover(string $hash): void
    {
        if (isset($this->transfers[$hash])) {
            unset($this->transfers[$hash]);
        }
    }
}
