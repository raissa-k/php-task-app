<?php
// Front controller: entra aqui toda requisição web

declare(strict_types=1);

/** Front controller do app. Recebe o request e envia para o Router. */
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if (!str_starts_with($path, '/api')) {
	session_start();
}

/*
 * Security headers: defesas demonstradas aqui.
 *
 * X-Frame-Options: impede que a página seja embutida em <iframe> de outro domínio.
 * Protege contra clickjacking: o atacante não consegue sobrepor um iframe invisível do nosso app sobre o site dele.
 *
 * X-Content-Type-Options: impede que o browser "adivinhe" o tipo do conteúdo (MIME sniffing). Sem isso, um arquivo de texto com conteúdo HTML poderia ser executado como HTML.
 *
 * Referrer-Policy: limita o que o browser envia no header Referer ao navegar pra outro domínio.
 * "strict-origin-when-cross-origin": envia a origem completa em requests same-origin, só a origem (sem path) em cross-origin HTTPS, e nada em cross-origin HTTP.
 * Evita vazar paths internos (ex: /admin/viacoes/42/edit) em links pro exterior.
 *
 * Content-Security-Policy: declara de onde scripts, estilos e imagens podem ser carregados.
 * 'unsafe-inline' em script-src é necessário por causa do onsubmit="return confirm(...)" nos forms de delete.
 * Pesquise: "CSP nonce", "clickjacking", "MIME sniffing", "Referrer-Policy".
*/
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self'; img-src 'self' data:; object-src 'none'");

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

use App\Core\Router;

$router = new Router();

// ---- Middleware de segurança ----

/*
 * CSRF: valida o token em todas as requisições que modificam dados no servidor.
 * Não se aplica à /api, que autentica por token no header e não usa sessão.
 *
 * Por que incluir PUT e DELETE além de POST?
 * Formulários HTML só conseguem enviar GET ou POST, mas usamos _method spoofing pra simular PUT e DELETE (veja a seção "Method spoofing" abaixo).
 * Após o spoofing, o Router recebe PUT ou DELETE; por isso o middleware precisa cobrir esses métodos também.
 *
 * O CsrfMiddleware internamente checa $_SERVER['REQUEST_METHOD'] (o método REAL enviado pelo browser).
 * Como requisições spoofadas chegam como POST no servidor, ele valida o token corretamente.
 * Para requisições PUT/DELETE reais (ex: de uma API mobile com sessão), o check interno vê que não é POST e pula (mais seguro).
*/
$router->addMiddleware('/', [\App\Middleware\CsrfMiddleware::class, 'verify'], ['POST', 'PUT', 'DELETE', 'PATCH']);

/*
 * Auth: bloqueia acesso às rotas /admin sem sessão ativa.
 * Redireciona pro login com flash message explicando o motivo.
 * O prefixo /admin cobre todas as rotas do painel (viacoes, historico, usuarios).
*/
$router->addMiddleware('/admin', [\App\Middleware\AuthMiddleware::class, 'ensure']);

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

/*
 * Method spoofing: browsers só conseguem enviar GET e POST via <form>.
 * Métodos como PUT e DELETE são semanticamente corretos pra update e exclusão, mas o HTML não os suporta nativamente.
 * Solução padrão: incluir um campo oculto no form com o método desejado:
 * <input type="hidden" name="_method" value="DELETE">
 * Este bloco detecta esse campo e substitui o método antes de despachar.
 * O Router então enxerga DELETE (ou PUT) e aciona o handler correto.
 *
 * Segurança: o token CSRF é validado ANTES do spoofing?
 * Não exatamente, o middleware roda dentro de dispatch(), já com o método reescrito.
 * Mas o CsrfMiddleware checa $_SERVER['REQUEST_METHOD'] (sempre POST do browser), então o token é validado corretamente independente do spoofing.
 * Pesquise: "HTTP method override", "method spoofing", "REST verbs".
 */
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST' && isset($_POST['_method'])) {
    $spoofed = strtoupper((string) $_POST['_method']);
    if (in_array($spoofed, ['PUT', 'DELETE', 'PATCH'], true)) {
        $method = $spoofed;
    }
}

$router->dispatch($method, $uri);
