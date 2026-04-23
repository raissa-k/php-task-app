<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Task;
use App\Services\TaskService;

/** Controller da API para leitura de tasks em JSON. */
final class TaskApiController
{
    private TaskService $tasks;

    public function __construct(?TaskService $tasks = null)
    {
        $this->tasks = $tasks ?? new TaskService();
    }

    /** Retorna todas as tasks. */
    public function index(): void
    {
        $items = [];

        foreach ($this->tasks->all() as $task) {
            $items[] = $this->toArray($task);
        }

        $this->json(200, [
            'ok' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    /** Retorna uma task por id. */
    public function show(int $id): void
    {
        $task = $this->tasks->find($id);

        if ($task === null) {
            $this->json(404, [
                'ok' => false,
                'message' => 'Task not found.',
            ]);
            return;
        }

        $this->json(200, [
            'ok' => true,
            'data' => $this->toArray($task),
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'is_done' => $task->isDone,
            'created_at' => $task->createdAt,
            'updated_at' => $task->updatedAt,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
