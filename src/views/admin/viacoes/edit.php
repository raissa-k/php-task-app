<?php
// View de edição de viação

declare(strict_types=1);

/** @var \App\Models\Viacao $viacao Dados atuais da viação */
/** @var array $errors Lista de erros de validação */
/** @var array $old Dados do form anterior (pra repopular em caso de erro de validação) */
?>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

<p>
    <a href="/admin/historico?viacao_id=<?= $viacao->id ?>">Ver histórico desta viação</a>
</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <ul class="error-list">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/viacoes/<?= $viacao->id ?>" enctype="multipart/form-data">
    <?= \App\Core\View::csrfField() ?>
    <?php
    /*
     * _method=PUT: browsers enviam POST, o index.php reescreve pra PUT antes de despachar.
     * PUT é o verbo HTTP correto pra atualizar um recurso existente.
     * Pesquise: "HTTP PUT vs POST", "method spoofing".
    */ ?>
    <?= \App\Core\View::methodField('PUT') ?>

    <div class="form-group">
        <label for="nome">Nome</label>
        <input
            type="text"
            id="nome"
            name="nome"
            value="<?= htmlspecialchars($old['nome'] ?? $viacao->nome, ENT_QUOTES, 'UTF-8') ?>"
            required
            maxlength="255"
        >
    </div>

    <div class="form-group">
        <label for="cidade">Cidade</label>
        <input
            type="text"
            id="cidade"
            name="cidade"
            value="<?= htmlspecialchars($old['cidade'] ?? $viacao->cidade, ENT_QUOTES, 'UTF-8') ?>"
            required
            maxlength="255"
        >
    </div>

    <div class="form-group">
        <label>
            <input
                type="checkbox"
                name="ativa"
                value="1"
                <?= ($old['ativa'] ?? $viacao->ativa) ? 'checked' : '' ?>
            >
            Viação ativa (aparece na home pública)
        </label>
    </div>

    <div class="form-group">
        <label for="logo">Logo (JPG, PNG ou WEBP, máx. 2&nbsp;MB)</label>

        <?php if ($viacao->logo !== null): ?>
            <p class="small muted">
                Logo atual:
                <img
                    class="logo-preview"
                    src="/uploads/<?= htmlspecialchars($viacao->logo, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Logo atual da <?= htmlspecialchars($viacao->nome, ENT_QUOTES, 'UTF-8') ?>"
                >
            </p>
            <p class="small muted">Envie um novo arquivo pra substituir o logo atual.</p>
        <?php endif; ?>

        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
    </div>

    <div class="form-actions">
        <button type="submit">Salvar alterações</button>
        <a href="/admin/viacoes">Cancelar</a>
    </div>

</form>
