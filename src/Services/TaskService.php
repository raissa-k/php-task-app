<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use PDO;

/** Concentra operacoes de CRUD e mapeamento de Task. */
final class TaskService
{
    private PDO $pdo;

    /** @param PDO|null $pdo Permite injetar a conexao em testes. */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \getPdo();
    }

    /** @return list<Task> Retorna todas as tasks, da mais nova para a mais antiga. */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tasks ORDER BY id DESC');
        $rows = $stmt->fetchAll();

        $tasks = [];
        foreach ($rows as $row) {
            $tasks[] = Task::fromRow($row);
        }

        return $tasks;
    }

    /** @return Task|null Retorna null quando o id nao existe. */
    public function find(int $id): ?Task
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return Task::fromRow($row);
    }

    /** Cria uma task e retorna o id gerado. */
    public function create(string $title, ?string $description, bool $isDone): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (title, description, is_done) VALUES (:title, :description, :is_done)'
        );

        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'is_done' => $isDone ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Atualiza os campos de uma task existente. */
    public function update(int $id, string $title, ?string $description, bool $isDone): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tasks SET title = :title, description = :description, is_done = :is_done WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'is_done' => $isDone ? 1 : 0,
        ]);
    }

    /** Remove uma task pelo id. */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
