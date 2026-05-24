<?php
// Model de viação: representa a entidade principal do demo

declare(strict_types=1);

namespace App\Models;

/** Representa uma viação carregada do banco. */
final class Viacao
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $cidade,
        public bool $ativa,
        public ?string $logo,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            nome: (string) $row['nome'],
            cidade: (string) $row['cidade'],
            ativa: ((int) ($row['ativa'] ?? $row['is_active'] ?? 0)) === 1,
            logo: $row['logo'] !== null ? (string) $row['logo'] : null,
            createdAt: (string) $row['created_at'],
            updatedAt: $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        );
    }

    /** Representa o modelo como array simples para saída JSON/Views. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cidade' => $this->cidade,
            'ativa' => $this->ativa,
            'logo' => $this->logo,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
