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

namespace Support\WebApplication;

use Generator;
use Illuminate\View\Factory;
use PRipple;
use ReflectionException;
use ReflectionMethod;
use Support\Http\HttpWorker;
use Support\Http\Request;
use Support\WebApplication\Exception\RouteExcept;
use Support\WebApplication\Exception\WebException;
use Support\WebApplication\Extends\Laravel;
use Support\WebApplication\Extends\Session\Session;
use Support\WebApplication\Extends\Session\SessionManager;
use Support\WebApplication\Std\MiddlewareStd;
use Throwable;

/**
 * Class WebApplication
 * 低耦合的方式避免Worker
 * 绑定路由规则并遵循HttpWorker的规范将处理器注入到Worker中
 */
class WebApplication
{
    private HttpWorker     $httpWorker;
    private RouteMap       $routeMap;
    private SessionManager $sessionManager;

    /**
     * WebApplication constructor.
     * @param HttpWorker $httpWorker
     * @param RouteMap   $routeMap
     * @param array      $config
     */
    public function __construct(HttpWorker $httpWorker, RouteMap $routeMap, array $config)
    {
        $this->httpWorker = $httpWorker;
        $this->routeMap   = $routeMap;
        if ($sessionPath = $config['SESSION_PATH'] ?? null) {
            $this->sessionManager = new SessionManager($sessionPath);
        }
        $viewPaths = [__DIR__ . '/Resources/Views'];
        if ($viewPath = $config['VIEW_PATH_BLADE'] ?? null) {
            $viewPaths[] = $viewPath;
        }
        $cachePath = PP_RUNTIME_PATH . '/cache';

        $laravel = Laravel::getInstance();
        $laravel->initViewEngine($viewPaths, $cachePath);
    }

    /**
     * 加载HttpWorker
     * @param HttpWorker $httpWorker
     * @param RouteMap   $routeMap
     * @param array      $config
     * @return void
     */
    public static function inject(HttpWorker $httpWorker, RouteMap $routeMap, array $config): void
    {
        $webApplication = new self($httpWorker, $routeMap, $config);

        /**
         * @throw Throwable
         */
        $httpWorker->defineRequestHandler(function (Request $request) use ($webApplication) {
            return $webApplication->requestHandler($request);
        });

        /**
         * @throw Throwable
         */
        $httpWorker->defineExceptionHandler(function (mixed $error, Request $request) use ($webApplication) {
            $webApplication->exceptionHandler($error, $request);
        });
    }

    /**
     * 请求处理
     * @param Request $request
     * @return Generator
     * @throws ReflectionException
     * @throws RouteExcept
     * @throws Throwable
     */
    private function requestHandler(Request $request): Generator
    {
        $request->injectDependencies(Request::class, $request);
        $laravel = Laravel::getInstance();
        foreach ($laravel->dependencyInjectionList as $key => $value) {
            $request->injectDependencies($key, $value);
        }
        if (!$router = $this->routeMap->match($request->method, trim($request->path, '/'))) {
            throw new RouteExcept('404 Not Found', 404);
        }
        $this->initSession($request);
        foreach ($router->getMiddlewares() as $middleware) {
            if (!$middlewareObject = $request->resolveDependencies($middleware)) {
                throw new WebException('500 Internal Server Error: class does not exist', 500);
            }
            /**
             * @var MiddlewareStd $middlewareObject
             */
            $middlewareObject->handle($request);
        }
        if (!class_exists($router->getPath())) {
            throw new RouteExcept("500 Internal Server Error: class {$router->getPath()} does not exist", 500);
        } elseif (!method_exists($router->getPath(), $router->getMethod())) {
            throw new RouteExcept("500 Internal Server Error: method {$router->getMethod()} does not exist", 500);
        }
        $params = $this->resolveRouteParams($router->getPath(), $router->getMethod(), $request);
        return call_user_func_array([$router->getPath(), $router->getMethod()], $params);
    }

    /**
     * 解析路由参数
     * @param string  $class
     * @param string  $method
     * @param Request $request
     * @return array
     * @throws ReflectionException
     * @throws Throwable
     */
    private function resolveRouteParams(string $class, string $method, Request $request): array
    {
        $reflectionMethod = new ReflectionMethod($class, $method);
        $parameters       = $reflectionMethod->getParameters();
        $params           = [];
        foreach ($parameters as $parameter) {
            $types = $parameter->getType()?->getName() ?? [];
            if (!$params[] = $request->resolveDependencies($types)) {
                throw new WebException('500 Internal Server Error: class does not exist', 500);
            }
        }
        return $params;
    }

    /**
     * 异常处理
     * @param mixed   $error
     * @param Request $request
     * @return void
     * @throws Throwable
     */
    private function exceptionHandler(mixed $error, Request $request): void
    {
        $blade = $request->resolveDependencies(Factory::class);
        /**
         * @var Factory $blade
         */
        $html = $blade->make('trace', [
            'title'  => $error->getMessage(),
            'traces' => $error->getTrace(),
            'file'   => $error->getFile(),
            'line'   => $error->getLine(),
        ])->render();
        $request->client->send($request->response->setStatusCode(500)->setBody($html)->__toString());
    }

    /**
     * @param Request $request
     * @return void
     */
    private function initSession(Request $request): void
    {
        if (isset($this->sessionManager)) {
            if (!$sessionID = $request->cookieArray['session'] ?? null) {
                $sessionID = md5(microtime(true) . PRipple::uniqueHash());
                $session   = $this->sessionManager->buildSession($sessionID);
                $request->response->setHeader('Set-Cookie', "session={$sessionID}; path=/; HttpOnly");
            } else {
                $session = $this->sessionManager->buildSession($sessionID);
            }
            $request->injectDependencies(Session::class, $session);
        }
    }
}
