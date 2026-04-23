<?php

declare(strict_types=1);

/** Front controller do app. Recebe o request e envia para o Router. */
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

use App\Core\Router;

$router = new Router();

require __DIR__ . '/../routes/web.php';

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');

$router->dispatch($method, $uri);
