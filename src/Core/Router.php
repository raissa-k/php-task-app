<?php

declare(strict_types=1);

namespace App\Core;

/** Router simples para registrar rotas GET e POST e despachar handlers. */
final class Router
{
    /**
     * @var array<string, list<array{pattern: string, regex: string, handler: callable|array{0: class-string, 1: string}}>>
     */
    private array $routes = [];

    /** Registra uma rota GET. */
    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** Registra uma rota POST. */
    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** Adiciona uma rota na tabela interna e converte pattern para regex. */
    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $method = strtoupper($method);
        $pattern = $this->normalizePath($pattern);

        $this->routes[$method] ??= [];
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $this->patternToRegex($pattern),
            'handler' => $handler,
        ];
    }

    /** Resolve a rota e executa o handler correspondente. */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = $this->normalizePath($path);

        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                if ($key === 'id' && is_string($value) && ctype_digit($value)) {
                    $params[$key] = (int) $value;
                    continue;
                }

                $params[$key] = $value;
            }

            $this->invoke($route['handler'], $params);
            return;
        }

        http_response_code(404);

        if (str_starts_with($path, '/api')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'message' => 'Route not found.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo 'Página não encontrada.';
    }

    /** @param array<string, mixed> $params */
    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method(...$params);
            return;
        }

        $handler(...$params);
    }

    /** Normaliza a URL para evitar duplicidade por barra final. */
    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /** Converte um pattern de rota em regex com grupos nomeados. */
    private function patternToRegex(string $pattern): string
    {
        $tmp = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn(array $m): string => '__PARAM__' . $m[1] . '__',
            $pattern
        );

        $quoted = preg_quote((string) $tmp, '#');

        $regex = preg_replace_callback(
            '#__PARAM__([a-zA-Z_][a-zA-Z0-9_]*)__#',
            static function (array $m): string {
                $name = $m[1];
                $paramPattern = $name === 'id' ? '\\d+' : '[^/]+';
                return '(?P<' . $name . '>' . $paramPattern . ')';
            },
            $quoted
        );

        return '#^' . (string) $regex . '$#';
    }
}
