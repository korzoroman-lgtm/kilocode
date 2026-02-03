<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router
 * Simple URL router with RESTful support
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $basePath = '';

    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Add GET route
     */
    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add POST route
     */
    public function post(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add PUT route
     */
    public function put(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Add DELETE route
     */
    public function delete(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Add PATCH route
     */
    public function patch(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Add route with any method
     */
    public function any(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('ANY', $path, $handler, $middleware);
    }

    /**
     * Add route definition
     */
    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): self
    {
        // Convert path parameters to regex
        $pattern = $this->pathToRegex($path);
        
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * Convert path with parameters to regex pattern
     */
    private function pathToRegex(string $path): string
    {
        // Remove base path
        $path = str_replace($this->basePath, '', $path);
        
        // Replace {param} with named capture groups
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        
        // Escape special regex characters except /
        $pattern = preg_quote($pattern, '/');
        
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch request to appropriate handler
     */
    public function dispatch(string $method, string $uri): array
    {
        // Remove query string
        $uri = strtok($uri, '?');
        
        // Remove trailing slash
        $uri = rtrim($uri, '/');
        
        if (empty($uri)) {
            $uri = '/';
        }

        // Remove base path if present
        if (!empty($this->basePath)) {
            $uri = str_replace($this->basePath, '', $uri);
            if (empty($uri)) {
                $uri = '/';
            }
        }

        foreach ($this->routes as $route) {
            // Check method match
            if ($route['method'] !== 'ANY' && $route['method'] !== strtoupper($method)) {
                continue;
            }

            // Check pattern match
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
                
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return [
            'handler' => null,
            'params' => [],
            'middleware' => [],
        ];
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Add global middleware
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Get global middleware
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Generate URL from route name and parameters
     */
    public function generateUrl(string $path, array $params = []): string
    {
        $url = $this->basePath . $path;
        
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', (string) $value, $url);
        }
        
        return $url;
    }
}
