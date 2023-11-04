<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\Http;

use Cclilshy\PRipple\Worker\NetWorker\Client;

/**
 *
 */
class RequestFactory
{
    public const INVALID = -1;
    public const COMPLETE = 2;
    public const INCOMPLETE = 1;

    /**
     * @var Http $httpService
     */
    private Http $httpService;

    /**
     * @var RequestSingle[] $singles
     */
    private array $singles = [];

    /**
     * @var RequestSingle[] $transfers
     */
    private array $transfers = [];

    /**
     * @param Http $httpService
     */
    public function __construct(Http $httpService)
    {
        $this->httpService = $httpService;
    }

    /**
     * @param string $context
     * @param Client $client
     * @return Request|null
     * @throws RequestSingleException
     */
    public function revolve(string $context, Client $client): ?Request
    {
        $clientHash = $client->getHash();
        if ($single = $this->transfers[$clientHash] ?? null) {
            if ($single->revolve($context)->statusCode === RequestFactory::COMPLETE) {
                unset($this->transfers[$clientHash]);
            }
            return null;
        }
        if (!$single = $this->singles[$clientHash] ?? null) {
            $this->singles[$clientHash] = $single = new RequestSingle($client);
        }

        $single->revolve($context);
        if (isset($single->method) && $single->method === 'POST' && $single->upload) {
            if ($single->statusCode !== RequestFactory::COMPLETE) {
                $this->transfers[$clientHash] = $single;
            }
            unset($this->singles[$clientHash]);
            return $single->build();
        }

        switch ($single->statusCode) {
            case RequestFactory::COMPLETE:
                unset($this->singles[$clientHash]);
                return $single->build();
            case RequestFactory::INVALID:
                $this->httpService->removeClient($client);
                unset($this->singles[$clientHash]);
                break;
            case RequestFactory::INCOMPLETE:
                break;
        }
        return null;
    }
}
