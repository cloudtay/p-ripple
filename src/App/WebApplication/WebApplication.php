<?php

namespace App\WebApplication;

use App\Http\HttpWorker;
use App\Http\Request;
use App\WebApplication\Exception\RouteExcept;
use App\WebApplication\Exception\WebException;
use App\WebApplication\Plugins\Blade;
use App\WebApplication\Std\MiddlewareStd;
use Generator;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Class WebApplication
 * 低耦合的方式避免Worker
 * 绑定路由规则并遵循HttpWorker的规范将处理器注入到Worker中
 */
class WebApplication
{
    private HttpWorker $httpWorker;
    private RouteMap   $routeMap;

    /**
     * WebApplication constructor.
     * @param HttpWorker $httpWorker
     * @param RouteMap   $routeMap
     */
    public function __construct(HttpWorker $httpWorker, RouteMap $routeMap)
    {
        $this->httpWorker = $httpWorker;
        $this->routeMap   = $routeMap;
    }

    /**
     * 加载HttpWorker
     * @param HttpWorker $httpWorker
     * @param RouteMap   $routeMap
     * @return void
     */
    public static function inject(HttpWorker $httpWorker, RouteMap $routeMap): void
    {
        $webApplication = new self($httpWorker, $routeMap);

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
        if (!$router = $this->routeMap->match($request->method, trim($request->path, '/'))) {
            throw new RouteExcept('404 Not Found', 404);
        }
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
        $blade = $request->resolveDependencies(Blade::class);
        /**
         * @var Blade $blade
         */
        $html = $blade->render('trace', [
            'title'  => $error->getMessage(),
            'traces' => $error->getTrace(),
            'file'   => $error->getFile(),
            'line'   => $error->getLine(),
        ]);
        $request->client->send($request->response->setStatusCode(500)->setBody($html)->__toString());
    }
}
