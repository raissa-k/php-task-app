<?php
// View admin de usuários: listagem somente leitura (sem CRUD por enquanto)

declare(strict_types=1);

/** @var list<\App\Models\Usuario> $usuarios */
?>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

<p class="muted small">
    Usuários são cadastrados via seed/banco diretamente. CRUD de usuários será implementado em breve.
</p>

<form class="filter-form" method="GET" action="/admin/usuarios">
    <div class="filter-field">
        <label class="filter-label" for="f-q">Busca</label>
        <input
            class="filter-input-lg"
            id="f-q"
            type="text"
            name="q"
            placeholder="Nome ou e-mail"
            value="<?= htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>
    <div class="filter-field">
        <span class="filter-label">&nbsp;</span>
        <div class="actions">
            <button type="submit">Filtrar</button>
            <a href="/admin/usuarios">Limpar</a>
        </div>
    </div>
</form>

<?php if (empty($usuarios)): ?>
    <p>Nenhum usuário cadastrado.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Cadastrado em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u->id ?></td>
                    <td><?= htmlspecialchars($u->nome, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u->email, ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="muted small"><?= htmlspecialchars($u->createdAt, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
