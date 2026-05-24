<?php
// Controller de autenticação: exibe login e gerencia sessão

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Http\Request;
use App\Http\ValidationException;
use App\Services\AuthService;
use App\Validators\LoginValidator;

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
        // Verifica rate limit antes de qualquer validação, não vale gastar tempo validando se o usuário já está bloqueado.
        if ($this->auth->isRateLimited()) {
            View::render('auth/login', [
                '_layout' => '_layout_public',
                'title'   => 'Entrar: Viações Demo',
                'errors'  => ['Muitas tentativas. Aguarde 1 minuto antes de tentar novamente.'],
                'old'     => ['email' => trim((string) ($_POST['email'] ?? ''))],
            ]);
            return;
        }

        /*
         * LoginValidator centraliza a validação de formato (e-mail válido, campos não-vazios).
         * O controller não precisa saber como validar, só precisa saber que, se validated() não lançar exception, os dados já estão limpos e prontos pra usar.
         */
        $request = new Request($_POST, [], new LoginValidator());

        try {
            $data = $request->validated();
        } catch (ValidationException $ve) {
            View::render('auth/login', [
                '_layout' => '_layout_public',
                'title'   => 'Entrar: Viações Demo',
                'errors'  => $ve->getErrors(),
                'old'     => ['email' => trim((string) ($_POST['email'] ?? ''))],
            ]);
            return;
        }

        if ($this->auth->attempt($data['email'], $data['password'])) {
            View::flash('success', 'Login efetuado.');
            View::redirect('/admin/viacoes');
            return;
        }

        View::render('auth/login', [
            '_layout' => '_layout_public',
            'title'   => 'Entrar: Viações Demo',
            'errors'  => ['E-mail ou senha incorretos.'],
            'old'     => ['email' => $data['email']],
        ]);
    }

    public function logout(): void
    {
        $this->auth->logout();
        View::flash('success', 'Logout realizado.');
        View::redirect('/');
    }
}
