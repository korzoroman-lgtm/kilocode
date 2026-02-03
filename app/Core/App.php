<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application Bootstrap
 * Main entry point for the web application
 */
class App
{
    private Router $router;
    private array $config;
    private bool $isApiRequest = false;

    public function __construct()
    {
        $this->config = Config::getInstance()->all();
        $this->router = new Router();
        $this->registerRoutes();
    }

    /**
     * Get the router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        try {
            // Set error reporting
            $this->setupErrorHandling();

            // Detect if this is an API request
            $this->isApiRequest = $this->detectApiRequest();

            // Get request method and URI
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = $_SERVER['REQUEST_URI'];

            // Set base path from configuration
            $basePath = $this->config['APP_URL'] ?? '';
            $this->router->setBasePath(parse_url($basePath, PHP_URL_PATH) ?? '');

            // Dispatch request
            $route = $this->router->dispatch($method, $uri);

            if ($route['handler'] === null) {
                $this->handleNotFound();
                return;
            }

            // Execute middleware
            $response = $this->executeMiddleware($route['middleware'], function () use ($route) {
                return $this->callHandler($route['handler']);
            });

            // Send response
            $this->sendResponse($response);

        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Register all application routes
     */
    private function registerRoutes(): void
    {
        // Include route definitions
        require_once dirname(__DIR__) . '/routes/web.php';
        require_once dirname(__DIR__) . '/routes/api.php';
        require_once dirname(__DIR__) . '/routes/admin.php';
    }

    /**
     * Detect if request is API call
     */
    private function detectApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_starts_with($uri, '/api/') || 
               str_starts_with($uri, '/api');
    }

    /**
     * Setup error and exception handling
     */
    private function setupErrorHandling(): void
    {
        if ($this->config['APP_DEBUG'] ?? 'false' === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }

        set_exception_handler(function (\Throwable $e) {
            $this->handleException($e);
        });
    }

    /**
     * Execute middleware chain
     */
    private function executeMiddleware(array $middleware, callable $final): mixed
    {
        $next = $final;
        
        foreach (array_reverse($middleware) as $mw) {
            $current = $next;
            $next = function ($request) use ($mw, $current) {
                return $mw($request, $current);
            };
        }

        return $next($this->getRequest());
    }

    /**
     * Call route handler
     */
    private function callHandler(callable|array $handler): mixed
    {
        if (is_callable($handler)) {
            return $handler();
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = is_string($class) ? new $class() : $class;
            return $controller->$method();
        }

        return null;
    }

    /**
     * Get current request data
     */
    private function getRequest(): array
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'query' => $_GET,
            'body' => $this->getRequestBody(),
            'headers' => $this->getRequestHeaders(),
            'session' => Session::getInstance(),
        ];
    }

    /**
     * Get request body as array
     */
    private function getRequestBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }
        
        return $_POST;
    }

    /**
     * Get request headers
     */
    private function getRequestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($header)] = $value;
            }
        }
        
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        return $headers;
    }

    /**
     * Send HTTP response
     */
    private function sendResponse(mixed $response): void
    {
        if ($response instanceof Response) {
            $response->send();
            return;
        }

        if (is_array($response)) {
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        if (is_string($response)) {
            echo $response;
            return;
        }

        if ($response === null) {
            http_response_code(204);
            return;
        }
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(): void
    {
        if ($this->isApiRequest) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo $this->renderErrorPage(404, 'Page Not Found', 'The page you are looking for does not exist.');
        }
    }

    /**
     * Handle exceptions
     */
    private function handleException(\Throwable $e): void
    {
        $logger = Logger::getInstance();
        $logger->error('Exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->isApiRequest) {
            http_response_code(500);
            $response = [
                'error' => 'Internal Server Error',
                'message' => $this->config['APP_DEBUG'] ? $e->getMessage() : 'An unexpected error occurred',
            ];
            
            if ($this->config['APP_DEBUG'] ?? 'false' === 'true') {
                $response['trace'] = explode("\n", $e->getTraceAsString());
            }
            
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo $this->renderErrorPage(500, 'Server Error', 'An unexpected error occurred.');
        }
    }

    /**
     * Render error page
     */
    private function renderErrorPage(int $code, string $title, string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$code} - {$title}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; padding: 2rem; }
        h1 { font-size: 4rem; margin: 0; color: #ff6b6b; }
        p { font-size: 1.2rem; color: #aaa; }
        a { color: #4ecdc4; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$code}</h1>
        <p>{$message}</p>
        <a href="/">Go Home</a>
    </div>
</body>
</html>
HTML;
    }
}
