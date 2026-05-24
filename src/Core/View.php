<?php
// Helper de view: renderiza templates, gerencia flash messages e tokens CSRF

declare(strict_types=1);

namespace App\Core;

/**
 * View: camada de apresentação do app.
 * Responsabilidades:
 * - Renderizar arquivos de template PHP com um layout
 * - Gerenciar flash messages (avisos que duram um request)
 * - Gerar e validar tokens CSRF pra formulários
 * - Facilitar redirecionamentos
 *
 * O que ela NÃO faz: lógica de negócio, acesso ao banco, validação de input.
 * Pesquise "SOLID principles" se quiser entender mais.
 */
final class View
{
    /**
     * Renderiza uma view num layout.
     *
     * @param string               $view  Caminho relativo à pasta views/ (sem .php)
     * @param array<string, mixed> $data  Variáveis passadas pra view e pro layout
     *
     * A chave especial '_layout' define qual arquivo de layout usar.
     * Ex: '_layout' => '_layout_public'  ->  src/views/_layout_public.php
     */
    public static function render(string $view, array $data = []): void
    {
        $basePath = dirname(__DIR__);
        $viewFile = $basePath . '/views/' . $view . '.php';
        $layout   = (string) ($data['_layout'] ?? '_layout'); // se não foi passado nada, carrega o _layout padrão
        unset($data['_layout']);
        $layoutFile = $basePath . '/views/' . $layout . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        if (!is_file($layoutFile)) {
            http_response_code(500);
            echo 'Layout não encontrado: ' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8');
            return;
        }

        /*
         * extract() com EXTR_SKIP transforma as chaves do $data em variáveis locais.
         * Ex: ['title' => 'Home', 'viacoes' => [...]] vira $title e $viacoes.
         * Por que EXTR_SKIP e não extract($data) simples?
         * EXTR_SKIP ignora chaves que já existem como variáveis, isso evita que $data sobrescreva variáveis internas como $basePath, $viewFile, $layout.
         * Sem EXTR_SKIP, um array malicioso poderia sobrescrever qualquer variável desse escopo.
         * Não é um risco real aqui (o $data vem de código interno), mas fica como exemplo de programação defensiva.
         * Pesquise "PHP extract EXTR_SKIP" e "variable injection" pra entender mais.
        */
        extract($data, EXTR_SKIP);

        // Captura o output da view em uma string, o layout vai injetar em $content
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require $layoutFile;
    }

    /** Redireciona para um path e encerra a requisição. */
    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    /**
     * Salva uma flash message na sessão para o próximo request.
     *
     * Flash messages são avisos temporários, eles aparecem uma vez e somem.
     * O padrão Post/Redirect/Get depende delas: você faz POST, redireciona, e no próximo GET a mensagem aparece sem risco de reenvio do form.
     * Pesquise "Post/Redirect/Get pattern".
     */
    public static function flash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['flash'] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /** Lê e remove a flash message da sessão (consome o aviso). */
    public static function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        if (!is_array($flash)) {
            return null;
        }

        if (!isset($flash['type'], $flash['message'])) {
            return null;
        }

        return [
            'type'    => (string) $flash['type'],
            'message' => (string) $flash['message'],
        ];
    }

    /**
     * Retorna o token CSRF da sessão, gerando um novo se necessário.
     *
     * O token é gerado com random_bytes(32). Isso é suficiente pra que seja impossível, na prática, adivinhar o valor.
     * Pesquise "CSPRNG" (Cryptographically Secure Pseudo-Random Number Generator).
     */
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    /**
     * Retorna um campo <input> oculto pra method spoofing.
     * HTML forms só suportam GET e POST. Pra usar PUT ou DELETE, incluímos esse campo no form.
     * O index.php detecta e reescreve o método antes de despachar pro Router.
     *
     * Uso:
     *   <form method="POST" action="/admin/viacoes/5">
     *       <?= View::csrfField() ?>
     *       <?= View::methodField('PUT') ?>
     *       ...
     *   </form>
     *
     * Pesquise: "HTTP method override", "method spoofing HTML form".
     */
    public static function methodField(string $method): string
    {
        return '<input type="hidden" name="_method" value="'
            . htmlspecialchars(strtoupper($method), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    /**
     * Retorna um campo <input> oculto com o token CSRF.
     * Use dentro de todo <form method="POST">:
     *
     *   <form method="POST" action="/viacoes">
     *       <?= View::csrfField() ?>
     *       ...
     *   </form>
     *
     * O valor é escapado com htmlspecialchars, sempre faça isso ao inserir valores dinâmicos em atributos HTML.
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            . '">';
    }
}
