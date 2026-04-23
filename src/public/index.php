<?php

declare(strict_types=1);

/** Front controller do app. Recebe o request e envia para o Router. */
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if (!str_starts_with($path, '/api')) {
	session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

use App\Core\Router;

$router = new Router();

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

$router->dispatch($method, $uri);
