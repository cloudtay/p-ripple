<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

declare(strict_types=1);

namespace Support\Http;

use Worker\Socket\TCPConnection;

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
     * @param string        $context
     * @param TCPConnection $client
     * @return Request|null
     * @throws RequestSingleException
     */
    public function revolve(string $context, TCPConnection $client): ?Request
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
