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

namespace Ext\Http;

use Worker\Socket\TCPConnection;


/**
 * 请求单例
 */
class RequestSingle
{
    /**
     * @var string
     */
    public string $hash;

    /**
     * @var string
     */
    public string $method;

    /**
     * @var string
     */
    public string $url;

    /**
     * @var string
     */
    public string $version;

    /**
     * @var string
     */
    public string $header;

    /**
     * @var array
     */
    public array $headers = [];

    /**
     * @var string
     */
    public string $body = '';

    /**
     * @var int
     */
    public int $bodyLength = 0;


    /**
     * @var int
     */
    public int $statusCode;

    /**
     * @var TCPConnection
     */
    public TCPConnection $client;


    /**
     * @var bool
     */
    public bool $upload = false;

    /**
     * @var string
     */
    public string $boundary = '';

    /**
     * @var RequestUpload
     */
    public RequestUpload $uploadHandler;

    /**
     * RequestSingle constructor.
     * @param TCPConnection $client
     */
    public function __construct(TCPConnection $client)
    {
        $this->hash = md5($client->getHash() . microtime(true) . mt_rand(0, 1000000));
        $this->client = $client;
        $this->statusCode = RequestFactory::INCOMPLETE;
    }

    /**
     * @param TCPConnection $client
     * @return self
     */
    public static function new(TCPConnection $client): RequestSingle
    {
        return new self($client);
    }

    /**
     * Push request body
     * @param string $context
     * @return RequestSingle
     * @throws RequestSingleException
     */
    public function revolve(string $context): self
    {
        if (!isset($this->method)) {
            if ($this->parseRequestHead($context)) {
                $context = $this->body;
            } else {
                return $this;
            }
        }
        switch ($this->method) {
            case 'GET':
                $this->statusCode = RequestFactory::COMPLETE;
                break;
            case 'POST':
                $this->bodyLength += strlen($context);
                if ($this->upload) {
                    $this->uploadHandler->push($context);
                } else {
                    $this->body .= $context;
                }
                if ($this->bodyLength === intval($this->headers['Content-Length'])) {
                    $this->statusCode = RequestFactory::COMPLETE;
                } elseif ($this->bodyLength > intval($this->headers['Content-Length'])) {
                    throw new RequestSingleException('Content-Length is not match');
                } else {
                    $this->statusCode = RequestFactory::INCOMPLETE;
                }
                break;
        }
        return $this;
    }

    /**
     * @param string $context
     * @return bool
     * @throws RequestSingleException
     */
    private function parseRequestHead(string $context): bool
    {
        if ($headerEnd = strpos($context, "\r\n\r\n")) {
            $this->header = substr($context, 0, $headerEnd);
            $this->body = substr($context, $headerEnd + 4);
            $baseContent = strtok($this->header, "\r\n");
            if (count($base = explode(' ', $baseContent)) !== 3) {
                $this->statusCode = RequestFactory::INVALID;
                return false;
            }
            $this->url = $base[1];
            $this->version = $base[2];
            $this->method = $base[0];
            while ($line = strtok("\r\n")) {
                $lineParam = explode(': ', $line, 2);
                if (count($lineParam) >= 2) {
                    $this->headers[$lineParam[0]] = $lineParam[1];
                }
            }
            if ($this->method === 'GET') {
                $this->statusCode = RequestFactory::COMPLETE;
                return true;
            }
            if (!isset($this->headers['Content-Length'])) {
                throw new RequestSingleException('Content-Length is not set');
            }
            # 初次解析POST类型
            if (!isset($this->headers['Content-Type'])) {
                throw new RequestSingleException('Content-Type is not set');
            }
            $contentType = $this->headers['Content-Type'];
            if (str_contains($contentType, 'multipart/form-data')) {
                preg_match('/boundary=(.*)$/', $contentType, $matches);
                if (isset($matches[1])) {
                    $this->boundary = $matches[1];
                    $this->upload = true;
                    $this->uploadHandler = new RequestUpload($this, $this->boundary);
                    $this->uploadHandler->push($this->body);
                    $this->body = '';
                } else {
                    throw new RequestSingleException('boundary is not set');
                }
            }
            return true;
        } else {
            $this->body .= $context;
        }
        return false;
    }

    /**
     * 打包请求
     * @return Request
     */
    public function build(): Request
    {
        return new Request($this);
    }
}
