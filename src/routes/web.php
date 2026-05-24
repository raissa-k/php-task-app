<?php
// Rotas web do app (páginas HTML)

declare(strict_types=1);

/** Arquivo de registro de rotas web. */
use App\Controllers\ViacaoController;
use App\Controllers\HomeController;

/** @var App\Core\Router $router */

/*
 * Cada rota combina um verbo HTTP + um path.
 * GET    -> leitura pura: exibe uma página ou lista. Pode ser repetido sem efeitos.
 * POST   -> criação de um novo recurso.
 * PUT    -> atualização de um recurso existente (substitui o estado atual).
 * DELETE -> remoção de um recurso.
 *
 * Essa semântica vem do protocolo HTTP e é a base do REST.
 * Seguir os verbos corretos facilita o entendimento da API por qualquer dev,
 * permite que proxies e caches se comportem corretamente,
 * e alinha com ferramentas como Postman, OpenAPI e gateways de API.
 * Forms HTML só enviam GET e POST, PUT e DELETE chegam via _method spoofing (campo oculto no form + reescrita no index.php).
 * Pesquise: "HTTP semantics", "REST resource design", "CRUD vs REST verbs".
*/

// Home pública
$router->get('/', [HomeController::class, 'index']);

// CRUD admin de viações. Prefixo /admin deixa explícito que é área restrita
$router->get('/admin/viacoes',           [ViacaoController::class, 'index']);   // lista
$router->get('/admin/viacoes/create',    [ViacaoController::class, 'create']);  // formulário de criação
$router->post('/admin/viacoes',          [ViacaoController::class, 'store']);   // salva nova viação
$router->get('/admin/viacoes/{id}/edit', [ViacaoController::class, 'edit']);    // formulário de edição
$router->put('/admin/viacoes/{id}',      [ViacaoController::class, 'update']);  // atualiza viação existente
$router->delete('/admin/viacoes/{id}',   [ViacaoController::class, 'destroy']); // remove viação

$router->get('/admin/historico', [\App\Controllers\HistoricoController::class, 'index']);


// Serve arquivos de upload (logos) armazenados fora do docroot
$router->get('/uploads/{filename}', [\App\Controllers\UploadController::class, 'serve']);

// Auth
$router->get('/login', [\App\Controllers\AuthController::class, 'loginForm']);
$router->post('/login', [\App\Controllers\AuthController::class, 'login']);
$router->post('/logout', [\App\Controllers\AuthController::class, 'logout']);

// Users
$router->get('/admin/usuarios', [\App\Controllers\UsuariosController::class, 'index']);
