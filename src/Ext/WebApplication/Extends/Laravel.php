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

namespace Ext\WebApplication\Extends;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine as ViewCompilerEngine;
use Illuminate\View\Engines\EngineResolver as ViewEngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder as ViewFileFinder;

/**
 * 模板引擎只在WebApplication中使用,要单拎
 * Class Laravel
 */
class Laravel
{
    /**
     * Laravel容器管理器
     * @var Container $container
     */
    public Container $container;

    /**
     * Laravel文件系统
     * @var Filesystem $filesystem
     */
    public Filesystem $filesystem;

    /**
     * Laravel事件调度器
     * @var Dispatcher $eventDispatcher
     */
    public Dispatcher $eventDispatcher;

    /**
     * 储存注册过的依赖
     * @var array $dependencyInjectionList
     */
    public array $dependencyInjectionList = [];

    /**
     * 初始化Laravel设计模式底层依赖
     */
    public function __construct()
    {
        $this->container       = new Container();
        $this->filesystem      = new Filesystem();
        $this->eventDispatcher = new Dispatcher($this->container);
    }

    /**
     * 注册模板引擎
     * @param array       $viewPaths
     * @param string|null $cachePath 缓存文件路径
     * @return void
     */
    public function initViewEngine(array $viewPaths, string|null $cachePath = '/tmp'): void
    {
        $bladeCompiler      = new BladeCompiler($this->filesystem, $cachePath);
        $viewEngineResolver = new ViewEngineResolver();
        $viewEngineResolver->register('blade', function () use ($bladeCompiler) {
            return new ViewCompilerEngine($bladeCompiler);
        });
        $viewFileFinder = new ViewFileFinder($this->filesystem, $viewPaths);
        $factory                                           = new ViewFactory($viewEngineResolver, $viewFileFinder, $this->eventDispatcher);
        $this->dependencyInjectionList[ViewFactory::class] = $factory;
    }
}
