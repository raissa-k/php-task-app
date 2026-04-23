<?php

declare(strict_types=1);

/** Arquivo de registro de rotas web. */
use App\Controllers\TaskController;

/** @var App\Core\Router $router */

$router->get('/', [TaskController::class, 'index']);
$router->get('/tasks', [TaskController::class, 'index']);

$router->get('/tasks/create', [TaskController::class, 'create']);
$router->post('/tasks', [TaskController::class, 'store']);

$router->get('/tasks/{id}/edit', [TaskController::class, 'edit']);
$router->post('/tasks/{id}', [TaskController::class, 'update']);
$router->post('/tasks/{id}/delete', [TaskController::class, 'destroy']);
