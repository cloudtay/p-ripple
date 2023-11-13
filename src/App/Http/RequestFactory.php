<?php
declare(strict_types=1);

namespace App\Http;

use Worker\NetWorker\Client;

/**
 * Http流工厂
 */
class RequestFactory
{
    public const INVALID    = -1;   # 传输异常
    public const COMPLETE   = 2;    # 传输完成
    public const INCOMPLETE = 1;    # 传输中

    /**
     * Http服务实体
     * @var HttpWorker $httpService
     */
    private HttpWorker $httpService;

    /**
     * 传输中的Request
     * @var RequestSingle[] $singles
     */
    private array $singles = [];

    /**
     * 已经解析但未完成的Request
     * @var RequestSingle[] $transfers
     */
    private array $transfers = [];

    /**
     * HttpWorker constructor.
     * 也许会用到.
     * @param HttpWorker $httpService
     */
    public function __construct(HttpWorker $httpService)
    {
        $this->httpService = $httpService;
    }

    /**
     * 解析请求
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
