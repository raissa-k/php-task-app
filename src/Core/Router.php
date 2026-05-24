<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router simples: registra rotas HTTP e despacha pro handler correto.
 *
 * Verbos suportados: GET, POST, PUT, DELETE (e PATCH via add()).
 * PUT e DELETE de forms HTML chegam via _method spoofing: o index.php
 * reescreve o método antes de chamar dispatch().
 *
 * Pesquise: "Front Controller pattern", "HTTP routing", "MVC request lifecycle".
 */
final class Router
{
    /**
     * @var array<string, list<array{pattern: string, regex: string, handler: callable|array{0: class-string, 1: string}}>>
     */
    private array $routes = [];

    /** @var list<array{pattern:string, middleware:callable, methods?:list<string>}> */
    private array $middleware = [];

    // GET: leitura pura. Nunca deve modificar dados e pode ser cacheado e repetido sem efeitos.
    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    // POST: criação de novo recurso. Não é idempotente (duas chamadas = dois recursos).
    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /*
     * PUT: atualização de recurso existente. É idempotente (repetir a mesma chamada tem o mesmo efeito que fazer uma vez).
     * Pesquise "HTTP idempotency".
     * De forms HTML, chega via _method spoofing (index.php faz a reescrita).
    */
    public function put(string $pattern, callable|array $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    /*
     * DELETE: remoção de recurso.
     * Também pode ser idempotente (deletar algo que já foi deletado não causando erro).
     * De forms HTML, chega via _method spoofing.
    */
    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    // Adiciona rota à tabela interna e gera regex
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

    // Despacha a rota que bate com o método e caminho
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = $this->normalizePath($path);

        // Executa middleware registrados globalmente por prefixo
        foreach ($this->middleware as $m) {
            $mPattern = $this->normalizePath($m['pattern']);
            if (!empty($m['methods']) && !in_array($method, array_map('strtoupper', $m['methods']), true)) {
                continue;
            }

            if (str_starts_with($path, $mPattern)) {
                $mw = $m['middleware'];
                // middleware pode redirecionar/terminar a request
                $mw($method, $path);
            }
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) { continue; }
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
            echo json_encode(['ok' => false, 'message' => 'Rota não encontrada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo 'Página não encontrada.';
    }

    // Invoca o handler (controller ou closure)
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

    // Normaliza caminho (remove slash sobrando)
    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') { $path = rtrim($path, '/'); }
        return $path;
    }

    // Converte pattern tipo /items/{id} em regex com grupo nomeado
    private function patternToRegex(string $pattern): string
    {
        $tmp = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', static fn(array $m): string => '__PARAM__' . $m[1] . '__', $pattern);
        $quoted = preg_quote((string) $tmp, '#');
        $regex = preg_replace_callback('#__PARAM__([a-zA-Z_][a-zA-Z0-9_]*)__#', static function (array $m): string {
            $name = $m[1];
            $paramPattern = $name === 'id' ? '\\d+' : '[^/]+';
            return '(?P<' . $name . '>' . $paramPattern . ')';
        }, $quoted);
        return '#^' . (string) $regex . '$#';
    }

    // Registra middleware por prefixo de path
    public function addMiddleware(string $pattern, callable $middleware, array $methods = []): void
    {
        $this->middleware[] = ['pattern' => $pattern, 'middleware' => $middleware, 'methods' => $methods];
    }
}
