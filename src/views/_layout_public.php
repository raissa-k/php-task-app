<?php
// Layout público: usado nas páginas que qualquer usuário pode acessar (home, etc.)
// É separado do _layout.php (admin) pra deixar o visual e o contexto bem diferentes.

declare(strict_types=1);

use App\Core\View;
use App\Services\AuthService;

/** @var string $content  Conteúdo renderizado pela view específica */
/** @var string|null $title Título da página */

// Flash message: aviso que dura só um request (ex: "Login necessário")
$flash = View::pullFlash();

// Verifica se tem usuário logado pra decidir o que mostrar no nav
$auth = new AuthService();
$user = $auth->user();
$isLogged = $user !== null;

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Viações Demo', ENT_QUOTES, 'UTF-8') ?></title>
    <?php /* app.css: base compartilhada (tipografia, reset, formulários) */ ?>
    <?php $cssV = static fn(string $f): int => (int) @filemtime(dirname(__DIR__) . '/public/' . $f); ?>
    <link rel="stylesheet" href="/app.css?v=<?= $cssV('app.css') ?>">
    <?php /* home.css: estilos específicos do layout público e da home */ ?>
    <link rel="stylesheet" href="/home.css?v=<?= $cssV('home.css') ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="pub-body">

<?php /* Nav pública: bem mais simples que a nav admin */ ?>
<header class="nav-pub">
    <nav class="container flex items-center justify-between">
        <a href="/" class="nav-logo">🚌 Viações</a>

        <div class="flex items-center gap">
            <?php if ($isLogged): ?>
                <?php /* Usuário logado vê atalhos pro painel */ ?>
                <a href="/admin/viacoes" class="nav-link">Painel</a>
                <a href="/admin/historico" class="nav-link">Histórico</a>
                <span class="nav-link">Olá, <?= htmlspecialchars($user->nome, ENT_QUOTES, 'UTF-8') ?></span>
                <form class="inline-form" method="POST" action="/logout">
                    <?= View::csrfField() ?>
                    <button class="btn btn-outline" type="submit">Sair</button>
                </form>
            <?php else: ?>
                <?php /* Visitante vê só o botão de entrar */ ?>
                <a href="/login" class="btn btn-primary">Entrar</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<?php /* Flash message (ex: "você precisa estar logado") */ ?>
<?php if ($flash !== null): ?>
    <div class="flash-pub <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php /* O conteúdo da view específica vai aqui */ ?>
<?= $content ?>

<footer class="footer-pub">
    <p>© <?= date('Y') ?> Viações Demo</p>
</footer>

</body>
</html>
