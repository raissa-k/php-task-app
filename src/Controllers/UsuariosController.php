<?php
// Controller administrativo de usuários: apenas listagem (sem CRUD por enquanto)

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\UsuarioRepository;

/*
 * Por que o controller usa o Repository diretamente aqui, sem um Service?
 * Regra geral: se não há regra de negócio, o controller pode usar o repository.
 * Aqui só listamos usuários sem lógica de criação, validação ou notificação.
 * Quando o CRUD de usuários for implementado, aí criamos um UsuarioService.
*/

final class UsuariosController
{
    private UsuarioRepository $repo;

    public function __construct(?UsuarioRepository $repo = null)
    {
        $this->repo = $repo ?? new UsuarioRepository();
    }

    /** Lista usuários com busca opcional por nome ou e-mail. */
    public function index(): void
    {
        $q = (string) ($_GET['q'] ?? '');
        $usuarios = $this->repo->all($q);

        View::render('admin/usuarios/index', [
            'title'    => 'Usuários',
            'usuarios' => $usuarios,
            'q'        => $q,
        ]);
    }
}
