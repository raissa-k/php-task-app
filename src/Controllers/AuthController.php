<?php
// Controller de autenticação: exibe login e gerencia sessão

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function loginForm(): void
    {
        View::render('auth/login', [
            '_layout' => '_layout_public',
            'title'   => 'Entrar: Viações Demo',
            'errors'  => [],
            'old'     => [],
        ]);
    }

    public function login(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($this->auth->attempt($email, $password)) {
            View::flash('success', 'Login efetuado.');
            View::redirect('/admin/viacoes');
            return;
        }

        View::render('auth/login', [
            '_layout' => '_layout_public',
            'title'   => 'Entrar: Viações Demo',
            'errors'  => ['E-mail ou senha incorretos.'],
            'old'     => ['email' => $email],
        ]);
    }

    public function logout(): void
    {
        $this->auth->logout();
        View::flash('success', 'Logout realizado.');
        View::redirect('/');
    }
}
