<?php
// Service de autenticação: isola toda lógica de sessão e login num único lugar

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use App\Repositories\UsuarioRepository;

/*
 * Por que existe um AuthService?
 * Autenticação é complexa e cheia de detalhes de segurança. Concentrar tudo aqui:
 * - Evita que cada controller invente seu próprio jeito de verificar login
 * - Facilita auditar e melhorar a segurança num único lugar
 * - Torna claro o "contrato": quem quer saber se o usuário está logado usa o AuthService
 * Pesquise "authentication vs authorization" pra entender a diferença.
 * AuthService cuida de autenticação (quem é você?).
 * AuthMiddleware cuida de autorização (você pode estar aqui?).
*/

final class AuthService
{
    private UsuarioRepository $users;

    public function __construct(?UsuarioRepository $users = null)
    {
        $this->users = $users ?? new UsuarioRepository();
    }

    /**
     * Inicia a sessão se ainda não foi iniciada, com flags de segurança.
     * Chamado internamente antes de qualquer operação que precisa de sessão.
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            /*
             * Flags de segurança do cookie de sessão:
             * httponly: JavaScript não consegue ler o cookie.
             * Isso protege contra XSS: mesmo que alguém injete um <script>, ele não consegue roubar o session ID.
             *
             * samesite: "Lax" - o cookie é enviado em navegações normais (clicar num link) mas
             * NÃO em requests cross-site iniciados por formulários de outros sites.
             * Isso é uma defesa contra CSRF, além do nosso CsrfMiddleware.
             *
             * Por que não "Strict"? Strict bloqueia o cookie mesmo em links normais, se você copiar a URL e abrir numa nova aba,
             * fica deslogado. "Lax" é um bom equilíbrio segurança x UX aqui no demo.
             * Pesquise "SameSite cookie", "HttpOnly flag", "session hijacking".
            */
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    /**
     * Verifica as credenciais e, se corretas, inicia a sessão autenticada.
     * Retorna true em sucesso, false se o email não existir ou a senha não bater.
     */
    public function attempt(string $email, string $password): bool
    {
        $this->startSession();

        /*
         * Rate limiting: bloqueia após 5 tentativas falhas no último minuto.
         * Limita força bruta sem exigir infraestrutura extra (banco, cache, etc).
         *
         * Limitação desta implementação: atacante numa aba diferente teria uma sessão diferente.
         * Em produção, usar controle mais direcionado como, por exemplo, por IP via cache (Redis) ou banco.
         * Pesquise "brute force attack", "rate limiting", "account lockout".
         */
        if ($this->isRateLimited()) {
            return false;
        }

        $usuario = $this->users->findByEmail($email);

        if ($usuario === null || $usuario->senha === null) {
            $this->recordFailedAttempt();
            return false;
        }

        /*
         * password_verify() compara a senha digitada com o hash armazenado.
         * NUNCA compare senhas com == ou ===, o hash muda a cada geração mesmo pra mesma senha.
         * password_verify() sabe como comparar corretamente. O tempo de password_verify() é propositalmente mais lento (custo do bcrypt).
         * Pesquise "bcrypt cost factor", "rainbow table attack".
        */
        if (!password_verify($password, $usuario->senha)) {
            $this->recordFailedAttempt();
            return false;
        }

        $this->clearRateLimit();

        /*
         * session_regenerate_id(true): troca o ID da sessão após o login.
         * Por quê? Para prevenir "session fixation attack".
         * Sem isso, um atacante poderia plantar um session ID no navegador antes do login, e depois usar esse mesmo ID pra sequestrar a sessão.
         * Regenerar o ID no login invalida qualquer ID pré-existente. O parâmetro true apaga a sessão antiga do servidor.
         * Pesquise "session fixation" e "session hijacking".
        */
        session_regenerate_id(true);

        $_SESSION['user_id']    = $usuario->id;
        $_SESSION['user_email'] = $usuario->email;

        return true;
    }

    /**
     * Retorna true se a sessão atual atingiu o limite de tentativas no último minuto.
     * Chamado pelo AuthController pra exibir uma mensagem de erro específica.
     */
    public function isRateLimited(): bool
    {
        $now      = time();
        $attempts = array_filter(
            $_SESSION['login_attempts'] ?? [],
            fn(int $t): bool => $t > $now - 60,
        );
        return count($attempts) >= 5;
    }

    /** Registra o timestamp da tentativa falha na sessão. */
    private function recordFailedAttempt(): void
    {
        $now      = time();
        $attempts = array_filter(
            $_SESSION['login_attempts'] ?? [],
            fn(int $t): bool => $t > $now - 60,
        );
        $attempts[]                 = $now;
        $_SESSION['login_attempts'] = array_values($attempts);
    }

    /** Limpa o histórico de tentativas após login bem-sucedido. */
    private function clearRateLimit(): void
    {
        unset($_SESSION['login_attempts']);
    }

    /** Remove a sessão do servidor e limpa o cookie no navegador. */
    public function logout(): void
    {
        $this->startSession();

        $_SESSION = [];

        /*
         * Expirar o cookie de sessão no navegador além de destruir a sessão no servidor.
         * Sem isso, o cookie ficaria salvo mesmo a sessão estando destruída.
         * Não é um risco crítico (o cookie inválido seria rejeitado), mas é legal.
        */
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path']   ?? '/',
                $params['domain'] ?? '',
                false,
                true,
            );
        }

        session_destroy();
    }

    /** Retorna true se há um usuário autenticado na sessão. */
    public function check(): bool
    {
        $this->startSession();
        return !empty($_SESSION['user_id']);
    }

    /** Retorna o ID do usuário logado, ou null se não estiver logado. */
    public function userId(): ?int
    {
        $this->startSession();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Retorna o objeto Usuario do usuário logado, ou null.
     * Útil pro layout exibir o nome do usuário sem precisar de uma query extra no controller.
     */
    public function user(): ?Usuario
    {
        $this->startSession();
        $id = $this->userId();
        if ($id === null) return null;
        return $this->users->find($id);
    }
}
