<?php
// View de cadastro de viação

declare(strict_types=1);

/** @var array $errors Lista de erros de validação */
/** @var array $old Dados do form anterior (pra repopular em caso de erro) */
?>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php /*
    Por que mostrar os erros ANTES do form?
    O usuário precisa saber o que corrigir antes de olhar pro campo.
    Além disso, leitores de tela leem a página de cima pra baixo, colocar o erro antes do campo melhora a acessibilidade.

    Pesquise "WAI-ARIA" pra entender como tornar forms mais acessíveis.
*/ ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <ul class="error-list">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php /*
    enctype="multipart/form-data" é OBRIGATÓRIO pra enviar arquivos.
    Sem ele, o PHP recebe um array $_FILES vazio e o upload silencia sem erro.
    É um clássico, sempre cheque se o enctype está certo!
*/ ?>
<form method="POST" action="/admin/viacoes" enctype="multipart/form-data">
    <?php
    /*
     * Token CSRF: campo oculto que prova que o form veio do nosso próprio site.
     * Sem isso, qualquer site poderia enganar o navegador do usuário a submeter esse form.
     * O CsrfMiddleware valida esse token em todo POST. Veja src/Middleware/CsrfMiddleware.php.
    */ ?>
    <?= \App\Core\View::csrfField() ?>

    <div class="form-group">
        <label for="nome">Nome</label>
        <input
            type="text"
            id="nome"
            name="nome"
            value="<?= htmlspecialchars($old['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
            value="<?= htmlspecialchars($old['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
            maxlength="255"
        >
    </div>

    <div class="form-group">
        <?php /*
            Checkbox: quando desmarcado, ele NÃO aparece no $_POST.
            Por isso o validator verifica a presença da chave, não só o valor.
            Isso é diferente de um input de texto, que sempre aparece (mesmo vazio).
        */ ?>
        <label>
            <input
                type="checkbox"
                name="ativa"
                value="1"
                <?= (!empty($old['ativa'])) ? 'checked' : '' ?>
            >
            Viação ativa (aparece na home pública)
        </label>
    </div>

    <div class="form-group">
        <label for="logo">Logo (opcional - JPG, PNG ou WEBP, máx. 2&nbsp;MB)</label>
        <?php /*
            O UploadService valida o tipo do arquivo pelo conteúdo real (finfo/MIME),
            não pela extensão. Isso evita que alguém renomeie um .php pra .jpg e faça upload.
            Pesquise "file upload security OWASP" pra saber mais sobre esse vetor de ataque.
        */ ?>
        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
    </div>

    <div class="form-actions">
        <button type="submit">Salvar viação</button>
        <a href="/admin/viacoes">Cancelar</a>
    </div>

</form>
