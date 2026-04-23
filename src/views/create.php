<?php

declare(strict_types=1);

/** @var list<string> $errors */
/** @var array{title: string, description: string, is_done: bool} $old */

?>

<h1>Criar task</h1>

<?php if ($errors !== []): ?>
    <div class="alert alert--danger">
        <p><strong>Corrija os erros:</strong></p>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/tasks">
    <div>
        <label for="title">Título</label><br>
        <input
            id="title"
            type="text"
            name="title"
            value="<?= htmlspecialchars($old['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
            maxlength="255"
        >
    </div>

    <div>
        <label for="description">Descrição</label><br>
        <textarea id="description" name="description" rows="4" cols="60"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <div>
        <label>
            <input type="checkbox" name="is_done" value="1" <?= !empty($old['is_done']) ? 'checked' : '' ?>>
            Concluída
        </label>
    </div>

    <button type="submit">Salvar</button>
</form>
