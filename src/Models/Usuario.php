<?php
// Model de usuário: mapeia colunas da tabela usuarios

declare(strict_types=1);

namespace App\Models;

final class Usuario
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $email,
        public string $createdAt,
        // Preenchido só quando a query inclui a coluna senha (ex: findByEmail).
        // Queries de listagem excluem senha intencionalmente.
        public ?string $senha = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            nome: (string) $row['nome'],
            email: (string) $row['email'],
            createdAt: (string) ($row['created_at'] ?? ''),
            senha: isset($row['senha']) ? (string) $row['senha'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'created_at' => $this->createdAt,
        ];
    }
}
