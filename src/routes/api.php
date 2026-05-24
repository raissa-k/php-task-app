<?php
// Rotas da API JSON (exemplo didático)

declare(strict_types=1);

use App\Controllers\Api\ViacaoApiController;

/** @var App\Core\Router $router */

// Atalho para listar viações
$router->get('/api', [ViacaoApiController::class, 'index']);

// Viações API
$router->get('/api/viacoes', [ViacaoApiController::class, 'index']);
$router->get('/api/viacoes/{id}', [ViacaoApiController::class, 'show']);
$router->post('/api/viacoes', [ViacaoApiController::class, 'store']);
$router->put('/api/viacoes/{id}', [ViacaoApiController::class, 'update']);
$router->delete('/api/viacoes/{id}', [ViacaoApiController::class, 'destroy']);
