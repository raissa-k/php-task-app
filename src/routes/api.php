<?php

declare(strict_types=1);

use App\Controllers\Api\TaskApiController;

/** @var App\Core\Router $router */

$router->get('/api', [TaskApiController::class, 'index']);
$router->get('/api/tasks', [TaskApiController::class, 'index']);
$router->get('/api/tasks/{id}', [TaskApiController::class, 'show']);
