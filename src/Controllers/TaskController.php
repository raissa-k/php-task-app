<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\TaskService;

/** Controla o fluxo HTTP de tasks e delega persistencia ao TaskService. */
final class TaskController
{
    /** Service usado para consultar e alterar tasks. */
    private TaskService $tasks;

    /** @param TaskService|null $tasks Permite injecao em testes. */
    public function __construct(?TaskService $tasks = null)
    {
        $this->tasks = $tasks ?? new TaskService();
    }

    /** Lista tasks e renderiza a tela principal. */
    public function index(): void
    {
        $tasks = $this->tasks->all();

        View::render('index', [
            'title' => 'Tasks',
            'tasks' => $tasks,
        ]);
    }

    /** Exibe o formulario de criacao. */
    public function create(): void
    {
        View::render('create', [
            'title' => 'Criar task',
            'errors' => [],
            'old' => [
                'title' => '',
                'description' => '',
                'is_done' => false,
            ],
        ]);
    }

    /** Processa o POST de criacao com PRG (Post Redirect Get). */
    public function store(): void
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isDone = isset($_POST['is_done']) && (string) $_POST['is_done'] === '1';

        $errors = $this->validate($title);

        if ($errors !== []) {
            View::render('create', [
                'title' => 'Criar task',
                'errors' => $errors,
                'old' => [
                    'title' => $title,
                    'description' => $description,
                    'is_done' => $isDone,
                ],
            ]);
            return;
        }

        $id = $this->tasks->create(
            $title,
            $description !== '' ? $description : null,
            $isDone
        );

        View::flash('success', 'Task criada com sucesso (#' . $id . ').');
        View::redirect('/tasks');
    }

    /** Exibe o formulario de edicao de uma task. */
    public function edit(int $id): void
    {
        $task = $this->tasks->find($id);

        if ($task === null) {
            http_response_code(404);
            echo 'Task não encontrada.';
            return;
        }

        View::render('edit', [
            'title' => 'Editar task',
            'task' => $task,
            'errors' => [],
            'old' => [
                'title' => $task->title,
                'description' => $task->description ?? '',
                'is_done' => $task->isDone,
            ],
        ]);
    }

    /** Processa o POST de atualizacao. */
    public function update(int $id): void
    {
        $task = $this->tasks->find($id);

        if ($task === null) {
            http_response_code(404);
            echo 'Task não encontrada.';
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isDone = isset($_POST['is_done']) && (string) $_POST['is_done'] === '1';

        $errors = $this->validate($title);

        if ($errors !== []) {
            View::render('edit', [
                'title' => 'Editar task',
                'task' => $task,
                'errors' => $errors,
                'old' => [
                    'title' => $title,
                    'description' => $description,
                    'is_done' => $isDone,
                ],
            ]);
            return;
        }

        $this->tasks->update(
            $id,
            $title,
            $description !== '' ? $description : null,
            $isDone
        );

        View::flash('success', 'Task atualizada com sucesso.');
        View::redirect('/tasks');
    }

    /** Remove uma task via POST para evitar delete por GET. */
    public function destroy(int $id): void
    {
        $task = $this->tasks->find($id);

        if ($task === null) {
            http_response_code(404);
            echo 'Task não encontrada.';
            return;
        }

        $this->tasks->delete($id);

        View::flash('success', 'Task removida com sucesso.');
        View::redirect('/tasks');
    }

    /** @return list<string> Retorna erros de validacao do titulo. */
    private function validate(string $title): array
    {
        $errors = [];

        if ($title === '') {
            $errors[] = 'O título é obrigatório.';
        }

        if (strlen($title) > 255) {
            $errors[] = 'O título deve ter no máximo 255 caracteres.';
        }

        return $errors;
    }
}
