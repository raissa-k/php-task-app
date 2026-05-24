<?php
// Front controller alternativo da API: mesma lógica do index.php mas sem sessão
//
// Por que esse arquivo existe se o index.php já cuida das rotas /api ?
//
// Depende de como o servidor é configurado:
//   - Com o .htaccess atual, /api/* vai pro index.php (sessão incluída)
//   - Se alguém acessar /api.php diretamente, cai aqui (sem sessão)
//
// Num projeto real, você provavelmente teria um único entry point.
// Esse arquivo existe como exemplo didático de um "API-only entry point".

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

use App\Core\Router;

// API não usa sessão (autenticação via token no header X-API-TOKEN)
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$router = new Router();
require __DIR__ . '/../routes/api.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/api');

$router->dispatch($method, $uri);
