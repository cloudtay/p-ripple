<?php

namespace App\WebApplication;

class Route
{
    /**
     * @var string $method
     */
    private string $method;

    /**
     * @var string $class
     */
    private string $class;

    /**
     * @var array $middlewares
     */
    private array $middlewares = [];

    /**
     * @param string $class
     * @param string $method
     */
    public function __construct(string $class, string $method)
    {
        $this->class = $class;
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->class;
    }

    /**
     * @return array
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @param string $middleware
     * @return void
     */
    public function middleware(string $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @param array $middlewares
     * @return void
     */
    public function middlewares(array $middlewares): void
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
    }
}