<?php
// Middleware simples: bloqueia acesso se não tiver login

declare(strict_types=1);

namespace App\Middleware;

use App\Core\View;
use App\Services\AuthService;

final class AuthMiddleware
{
    public static function ensure(): void
    {
        $auth = new AuthService();
        if (!$auth->check()) {
            // Flash pra avisar que precisa de login antes de acessar
            View::flash('danger', 'Você precisa estar logado pra acessar essa página.');
            header('Location: /login');
            exit;
        }
    }
}
