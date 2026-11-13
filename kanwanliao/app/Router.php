<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /** @param array<int, array{0:string,1:string,2:array<int, string>}> $routes */
    public function __construct(private readonly array $routes)
    {
    }

    /**
     * @return array{handler: array<int, string>, params: array<string, string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            [$routeMethod, $routePath, $handler] = $route;
            if ($routeMethod !== $method) {
                continue;
            }

            $quoted = preg_quote($routePath, '#');
            $quoted = str_replace(['\{', '\}'], ['{', '}'], $quoted);
            $regex = '#^' . preg_replace(
                '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
                '(?P<$1>[^/]+)',
                $quoted
            ) . '$#';

            if (preg_match($regex, $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return ['handler' => $handler, 'params' => $params];
        }

        return null;
    }
}
