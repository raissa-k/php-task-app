<?php
// Layout base das páginas administrativas (painel, viações, histórico, etc.)
// Páginas públicas usam _layout_public.php, cada contexto tem seu próprio layout.

declare(strict_types=1);

use App\Core\View;
use App\Services\AuthService;

/** @var string $content  Conteúdo renderizado pela view específica */
/** @var string|null $title Título da página */

$flash = View::pullFlash();
$auth  = new AuthService();
$user  = $auth->user();
$isLogged = $user !== null;

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Viações Admin', ENT_QUOTES, 'UTF-8') ?></title>
    <?php
    /*
     * app.css: estilos base compartilhados (tipografia, tabela, formulários, utilitárias)
     * admin.css: estilos específicos do painel admin (tabela admin, filtros, paginação)
     * Por que dois arquivos?
     * O usuário público não precisa baixar CSS de admin que ele nunca vai ver.
     * Pesquise "critical CSS" e "code splitting" pra entender mais sobre isso.
    */ ?>
    <?php
    /*
    * Cache-busting: usa a data de modificação do arquivo como versão.
     * O browser só re-baixa o CSS quando o ?v= muda. Sem isso, versões antigas ficam em cache e mudanças não aparecem sem Ctrl+Shift+R.
     * Pesquise "cache busting", "ETag", "Cache-Control".
     */
    $cssV = static fn(string $f): int => (int) @filemtime(dirname(__DIR__) . '/public/' . $f);
    ?>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/app.css?v=<?= $cssV('app.css') ?>">
    <link rel="stylesheet" href="/admin.css?v=<?= $cssV('admin.css') ?>">
</head>
<body>

<header>
    <nav>
        <?php if ($isLogged): ?>
            <?php /* Usuário logado vê o painel completo */ ?>
            <a href="/">Home</a>
            | <a href="/admin/viacoes">Viações</a>
            | <a href="/admin/viacoes/create">Nova viação</a>
            | <a href="/admin/historico">Histórico</a>
            | <a href="/admin/usuarios">Usuários</a>
            | <span class="muted">Olá, <?= htmlspecialchars($user->nome, ENT_QUOTES, 'UTF-8') ?></span>
            | <form class="inline-form" method="POST" action="/logout">
                <?= View::csrfField() ?>
                <button type="submit">Sair</button>
              </form>
        <?php else: ?>
            <?php
            /*
             * Usuário não logado só vê o link de login.
             * Mostrar links de admin pra quem não está logado é confuso e muda o comportamento esperado: clicar no link te redireciona pro login, sem nenhuma explicação de por quê.
             * Dica: middleware protege a rota mas não protege o que o usuário VÊ.
             * Segurança e UX são coisas separadas.
            */ ?>
            <a href="/">Home</a>
            | <a href="/login">Login</a>
        <?php endif; ?>
    </nav>
</header>

<?php if ($flash !== null): ?>
    <div class="flash">
        <div class="flash__box flash__box--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
            <strong><?= htmlspecialchars(strtoupper($flash['type']), ENT_QUOTES, 'UTF-8') ?>:</strong>
            <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
<?php endif; ?>

<main>
    <?= $content ?>
</main>

</body>
</html>
