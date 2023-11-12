<?php

namespace App\WebApplication;

use InvalidArgumentException;

class RouteMap
{
    public const GET     = 'GET';
    public const POST    = 'POST';
    public const PUT     = 'PUT';
    public const DELETE  = 'DELETE';
    public const PATCH   = 'PATCH';
    public const HEAD    = 'HEAD';
    public const OPTIONS = 'OPTIONS';
    public const TRACE   = 'TRACE';
    public const CONNECT = 'CONNECT';

    /**
     * @var Route[] $routes
     */
    private array $routes = [];

    /**
     * @param string $method
     * @param string $path
     * @param array $route
     * @return Route
     */
    public function define(string $method, string $path, array $route): Route
    {
        if (count($route) !== 2) {
            throw new InvalidArgumentException('Route must be an array with 2 elements');
        }
        list($class, $function) = $route;
        return $this->routes[$method][trim($path, '/')] = new Route($class, $function);
    }

    /**
     * @param string $method
     * @param string $path
     * @return Route|null
     */
    public function match(string $method, string $path): Route|null
    {
        return $this->routes[$method][$path] ?? null;
    }
}
