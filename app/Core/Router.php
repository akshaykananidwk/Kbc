<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

final class Router
{
    /** Matches a route constraint, allowing balanced brace quantifiers inside it. */
    private const CONSTRAINT = '(?:[^{}]|\{\d+(?:,\d*)?\})+';

    /** @var array<int,array{method:string,pattern:string,regex:string,params:array<int,string>,handler:mixed,middleware:array<int,string>,name:string}> */
    private array $routes = [];
    /** @var array<int,string> */
    private array $groupMiddleware = [];
    private string $groupPrefix = '';
    /** @var array<string,string> */
    private array $namedRoutes = [];

    /**
     * @param array<int,string> $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /** @param array<int,string> $middleware */
    public function get(string $pattern, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('GET', $pattern, $handler, $middleware, $name);
    }

    /** @param array<int,string> $middleware */
    public function post(string $pattern, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('POST', $pattern, $handler, $middleware, $name);
    }

    /** @param array<int,string> $middleware */
    public function put(string $pattern, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('PUT', $pattern, $handler, $middleware, $name);
    }

    /** @param array<int,string> $middleware */
    public function delete(string $pattern, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array<int,string> $methods
     * @param array<int,string> $middleware
     */
    public function match(array $methods, string $pattern, mixed $handler, array $middleware = [], string $name = ''): void
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $pattern, $handler, $middleware, $name);
        }
    }

    /** @param array<int,string> $middleware */
    private function add(string $method, string $pattern, mixed $handler, array $middleware, string $name): void
    {
        $full = $this->groupPrefix . '/' . trim($pattern, '/');
        $full = '/' . trim($full, '/');
        if ($full === '/') {
            $full = '/';
        }

        $params = [];
        // The constraint may itself contain brace quantifiers such as
        // {4,12}, so a plain [^}]+ would cut the pattern in half and
        // produce a regex that silently matches nothing.
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(' . self::CONSTRAINT . '))?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                $constraint = ($m[2] ?? '') !== '' ? $m[2] : '[^/]+';
                return '(' . $constraint . ')';
            },
            $full
        );

        $compiled = '#^' . $regex . '$#u';
        if (@preg_match($compiled, '') === false) {
            // Fail loudly at boot instead of serving a mysterious 404.
            throw new \RuntimeException('Route "' . $full . '" does not compile to a valid pattern.');
        }

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $full,
            'regex'      => $compiled,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'name'       => $name,
        ];

        if ($name !== '') {
            $this->namedRoutes[$name] = $full;
        }
    }

    /**
     * @return array{handler:mixed,middleware:array<int,string>,params:array<string,string>}
     * @throws HttpException
     */
    public function resolve(Request $request): array
    {
        $path = $request->path();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method()) {
                $methodMismatch = true;
                continue;
            }
            array_shift($matches);
            $params = [];
            foreach ($route['params'] as $index => $name) {
                $params[$name] = $matches[$index] ?? '';
            }
            return [
                'handler'    => $route['handler'],
                'middleware' => $route['middleware'],
                'params'     => $params,
            ];
        }

        if ($methodMismatch) {
            throw new HttpException(405, 'Method not allowed.');
        }
        throw new HttpException(404, 'Page not found.');
    }

    /** @param array<string,string|int> $params */
    public function route(string $name, array $params = []): string
    {
        $pattern = $this->namedRoutes[$name] ?? '/';
        foreach ($params as $key => $value) {
            $pattern = preg_replace(
                '/\{' . preg_quote((string) $key, '/') . '(?::' . self::CONSTRAINT . ')?\}/',
                (string) $value,
                $pattern
            ) ?? $pattern;
        }
        return $pattern;
    }
}
