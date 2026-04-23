<?php

declare(strict_types=1);

use App\Models\Task;

/** @var list<Task> $tasks */

?>

<h1>Tasks</h1>

<p><a href="/tasks/create">Criar task</a></p>

<?php if (count($tasks) === 0): ?>
    <p>Nenhuma task cadastrada.</p>
<?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
        <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Status</th>
            <th>Criada em</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tasks as $task): ?>
            <tr>
                <td><?= (int) $task->id ?></td>
                <td><?= htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $task->isDone ? 'Concluída' : 'Pendente' ?></td>
                <td><?= htmlspecialchars($task->createdAt, ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <span class="actions">
                        <a href="/tasks/<?= (int) $task->id ?>/edit">Editar</a>

                        <form method="post" action="/tasks/<?= (int) $task->id ?>/delete" onsubmit="return confirm('Remover esta task?');">
                            <button type="submit">Excluir</button>
                        </form>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
