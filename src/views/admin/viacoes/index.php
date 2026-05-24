<?php
// View de listagem admin das viações

declare(strict_types=1);

/** @var list<\App\Models\Viacao> $viacoes */
/** @var array $filters Filtros ativos (q, ativa) */
?>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

<p><a href="/admin/viacoes/create">Cadastrar nova viação</a></p>

<?php /* Filtros: GET pra URL compartilhável e botão "voltar" funcional */ ?>
<form class="filter-form" method="GET" action="/admin/viacoes">
    <div class="filter-field">
        <label class="filter-label" for="f-q">Busca</label>
        <input
            class="filter-input-lg"
            id="f-q"
            type="text"
            name="q"
            placeholder="Nome ou cidade"
            value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="filter-field">
        <label class="filter-label" for="f-ativa">Status</label>
        <select class="filter-input-md" id="f-ativa" name="ativa">
            <option value="">Todas</option>
            <option value="1" <?= ($filters['ativa'] ?? null) === true  ? 'selected' : '' ?>>Ativas</option>
            <option value="0" <?= ($filters['ativa'] ?? null) === false ? 'selected' : '' ?>>Inativas</option>
        </select>
    </div>

    <div class="filter-field">
        <span class="filter-label">&nbsp;</span>
        <div class="actions">
            <button type="submit">Filtrar</button>
            <a href="/admin/viacoes">Limpar</a>
        </div>
    </div>
</form>

<?php if (empty($viacoes)): ?>
    <p class="muted">Nenhuma viação cadastrada ainda.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Cidade</th>
                <th>Ativa</th>
                <th>Logo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($viacoes as $v): ?>
            <tr>
                <td class="small muted"><?= $v->id ?></td>
                <td><?= htmlspecialchars($v->nome, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($v->cidade, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $v->ativa ? 'Sim' : 'Não' ?></td>
                <td>
                    <?php if ($v->logo !== null): ?>
                        <img
                            class="logo-preview"
                            src="/uploads/<?= htmlspecialchars($v->logo, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Logo da <?= htmlspecialchars($v->nome, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    <?php else: ?>
                        <span class="muted small">---</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="/admin/viacoes/<?= $v->id ?>/edit">Editar</a>

                        <?php
                        /*
                         * Form de exclusão: usa method="POST" com _method=DELETE porque browsers não suportam DELETE em <form>.
                         * O index.php detecta o campo _method e reescreve o verbo antes de despachar pro Router, veja o "method spoofing".
                         * O confirm() é JavaScript nativo. Pesquise "window.confirm MDN".
                        */ ?>
                        <form
                            class="inline-form"
                            method="POST"
                            action="/admin/viacoes/<?= $v->id ?>"
                            onsubmit="return confirm('Confirmar exclusão de <?= htmlspecialchars(addslashes($v->nome), ENT_QUOTES, 'UTF-8') ?>?')"
                        >
                            <?= \App\Core\View::csrfField() ?>
                            <?= \App\Core\View::methodField('DELETE') ?>
                            <button type="submit">Excluir</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
