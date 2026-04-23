<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

use App\Core\Router;

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed. Use GET.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$router = new Router();
require __DIR__ . '/../routes/api.php';

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/api');

$router->dispatch($method, $uri);
