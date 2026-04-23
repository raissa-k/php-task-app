<?php

declare(strict_types=1);

namespace App\Models;

/** Representa uma task carregada do banco. */
final class Task
{
    /** Construtor com os campos ja normalizados. */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public bool $isDone,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row Mapeia uma linha do PDO para Task. */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            title: (string) $row['title'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            isDone: ((int) $row['is_done']) === 1,
            createdAt: (string) $row['created_at'],
            updatedAt: $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        );
    }
}
