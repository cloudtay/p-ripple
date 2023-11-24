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

namespace Ext\PDOProxy;

use Worker\Prop\Build;

/**
 * PDO请求包体
 */
class PDOBuild extends Build
{
    public const EVENT_QUERY             = 'pdo.proxy.query';
    public const EVENT_BEGIN_TRANSACTION = 'pdo.proxy.beginTransaction';
    public const EVENT_COMMIT            = 'pdo.proxy.commit';
    public const EVENT_ROLL_BACK         = 'pdo.proxy.rollBack';
    public const EVENT_TRANSACTION       = 'pdo.proxy.transaction';

    /**
     * @var string|mixed
     */
    public string $query;

    /**
     * @var array|mixed
     */
    public array $bindings;

    /**
     * @var array|mixed
     */
    public array $bindParams;

    /**
     * @param string $name
     * @param mixed  $data
     * @param string $hash
     */
    public function __construct(string $name, mixed $data, string $hash)
    {
        parent::__construct($name, $data, $hash);
        if ($name === PDOBuild::EVENT_QUERY) {
            $this->query      = $data['query'];
            $this->bindings   = $data['bindings'];
            $this->bindParams = $data['bindParams'];
        }
    }

    /**
     * @param string $hash
     * @return PDOBuild
     */
    public static function beginTransaction(string $hash): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_BEGIN_TRANSACTION, null, $hash);
    }

    /**
     * @param string     $hash
     * @param string     $query
     * @param array|null $bindings
     * @param array|null $bindParams
     * @return PDOBuild
     */
    public static function query(string $hash, string $query, array|null $bindings = [], array|null $bindParams = []): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_QUERY, [
            'query'      => $query,
            'bindings'   => $bindings,
            'bindParams' => $bindParams
        ], $hash);
    }

    /**
     * @param string $hash
     * @return PDOBuild
     */
    public static function commit(string $hash): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_COMMIT, null, $hash);
    }

    /**
     *
     * @param string $hash
     * @return PDOBuild
     */
    public static function rollBack(string $hash): PDOBuild
    {
        return PDOBuild::new(PDOBuild::EVENT_ROLL_BACK, null, $hash);
    }
}
