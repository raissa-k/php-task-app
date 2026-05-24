<?php
// Repository de usuários: concentra queries relacionadas a usuarios

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Usuario;
use PDO;

final class UsuarioRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \getPdo();
    }

    /**
     * Lista usuários com busca opcional por nome ou e-mail.
     *
     * @return list<Usuario>
     */
    public function all(string $q = ''): array
    {
        if ($q !== '') {
            // addcslashes escapa % e `_` que o MySQL interpreta como wildcards no LIKE.
            $escaped = addcslashes($q, '%_');
            $stmt = $this->pdo->prepare(
                'SELECT id, nome, email, created_at FROM usuarios
                 WHERE nome LIKE :q OR email LIKE :q2
                 ORDER BY id ASC'
            );
            $stmt->execute(['q' => '%' . $escaped . '%', 'q2' => '%' . $escaped . '%']);
        } else {
            $stmt = $this->pdo->query('SELECT id, nome, email, created_at FROM usuarios ORDER BY id ASC');
        }
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $out[] = Usuario::fromRow($row);
        }
        return $out;
    }

    public function find(int $id): ?Usuario
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, email, created_at FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return Usuario::fromRow($row);
    }

    public function findByEmail(string $email): ?Usuario
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return is_array($row) ? Usuario::fromRow($row) : null;
    }

    public function create(string $nome, string $email, string $senhaHash): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)');
        $stmt->execute(['nome' => $nome, 'email' => $email, 'senha' => $senhaHash]);
        return (int) $this->pdo->lastInsertId();
    }
}
