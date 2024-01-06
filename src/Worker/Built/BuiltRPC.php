<?php declare(strict_types=1);
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


namespace Cclilshy\PRipple\Worker\Built;

use Cclilshy\PRipple\Core\Net\Exception;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\Filesystem\Exception\FileException;
use Cclilshy\PRipple\Protocol\Slice;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Publisher;
use Cclilshy\PRipple\Worker\Socket\TCPConnection;
use Cclilshy\PRipple\Worker\WorkerNet;
use Override;
use function call_user_func_array;
use function json_decode;
use function json_encode;
use function method_exists;

/**
 * @class BuiltRPC
 */
class BuiltRPC extends WorkerNet implements WorkerInterface
{
    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct($name, Slice::class);
    }

    /**
     * @param string        $context
     * @param TCPConnection $TCPConnection
     * @return void
     */
    #[Override] protected function onMessage(string $context, TCPConnection $TCPConnection): void
    {
        $jsonRequest = json_decode($context);
        if (isset($jsonRequest->method)) {
            if (method_exists($this, $jsonRequest->method)) {
                $jsonRequest->params[] = Publisher::package($TCPConnection, $jsonRequest->id);
                $result                = call_user_func_array([$this, $jsonRequest->method], $jsonRequest->params ?? []);
                $packet                = json_encode([
                    'version' => '2.0',
                    'result'  => $result,
                    'id'      => $jsonRequest->id ?? null
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            } elseif (function_exists($jsonRequest)) {
                $jsonRequest->params[] = Publisher::package($TCPConnection, $jsonRequest->id);
                $result                = call_user_func_array($jsonRequest->method, $jsonRequest->params ?? []);
                $packet                = json_encode([
                    'version' => '2.0',
                    'result'  => $result,
                    'id'      => $jsonRequest->id ?? null
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            } else {
                $packet = json_encode([
                    'version' => '2.0',
                    'error'   => [
                        'code'    => -32601,
                        'message' => 'Method not found'
                    ],
                    'id'      => $jsonRequest->id ?? null
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            try {
                $this->slice->send($TCPConnection, $packet);
            } catch (FileException|Exception $exception) {
                Output::printException($exception);
                $this->removeTCPConnection($TCPConnection);
            }
        }
    }
}
