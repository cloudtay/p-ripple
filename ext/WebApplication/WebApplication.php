<?php

namespace recycle\WebApplication;

use Core\Map\ExtendMap;
use Generator;
use Illuminate\View\Factory;
use PRipple;
use recycle\Extends\Laravel;
use recycle\Extends\Session\Session;
use recycle\Extends\Session\SessionManager;
use recycle\Http\HttpWorker;
use recycle\Http\Request;
use recycle\WebApplication\Exception\RouteExcept;
use recycle\WebApplication\Exception\WebException;
use recycle\WebApplication\Std\MiddlewareStd;
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
        $viewPaths = [PP_ROOT_PATH . '/App/WebApplication/Resources/Views'];
        if ($viewPath = $config['VIEW_PATH_BLADE'] ?? null) {
            $viewPaths[] = $viewPath;
        }
        $cachePath = PP_RUNTIME_PATH . '/cache';
        /**
         * 注册模板引擎
         * @var Laravel $laravel
         */
        $laravel = ExtendMap::get(Laravel::class);
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
        /**
         * @var Laravel $laravel
         */
        $laravel = ExtendMap::get(Laravel::class);
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
