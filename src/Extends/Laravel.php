<?php

namespace Extends;

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
     * @param string $viewPath  视图文件路径
     * @param string $cachePath 缓存文件路径
     * @return void
     */
    public function initViewEngine(string $viewPath, string $cachePath): void
    {
        $bladeCompiler      = new BladeCompiler($this->filesystem, $cachePath);
        $viewEngineResolver = new ViewEngineResolver();
        $viewEngineResolver->register('blade', function () use ($bladeCompiler) {
            return new ViewCompilerEngine($bladeCompiler);
        });
        $viewFileFinder                                    = new ViewFileFinder($this->filesystem, [$viewPath]);
        $factory                                           = new ViewFactory($viewEngineResolver, $viewFileFinder, $this->eventDispatcher);
        $this->dependencyInjectionList[ViewFactory::class] = $factory;
    }
}
