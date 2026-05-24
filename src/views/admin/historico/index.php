<?php
// View admin do histórico: filtros + tabela de alterações

declare(strict_types=1);

/** @var list<\App\Models\Historico> $historico */
/** @var array  $filters Filtros ativos (acao, date_from, date_to, q) */
?>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

<?php /*
    Form de filtro usa GET (não POST) pra dois motivos:
    1. A URL vira "compartilhável", você pode copiar e enviar pra outra pessoa
    2. O browser grava no histórico, então o botão "voltar" funciona corretamente

    POST é certo quando você está MUDANDO dados no servidor.
    GET é certo quando você está só LENDO/FILTRANDO.
    Pesquise "HTTP idempotency" pra entender essa distinção.
*/ ?>
<form class="filter-form" method="GET" action="/admin/historico">

    <?php
    /*
     * viacao_id preservado como campo oculto: o link "Ver histórico desta viação" na tela de edição passa esse parâmetro, e ele precisa sobreviver ao submit do form.
    */ ?>
    <?php if (!empty($filters['viacao_id'])): ?>
        <input type="hidden" name="viacao_id" value="<?= (int) $filters['viacao_id'] ?>">
    <?php endif; ?>

    <div class="filter-field">
        <label class="filter-label" for="f-q">Busca</label>
        <?php
        /*
         * Campo unificado: pesquisa ao mesmo tempo no nome da viação, nome do usuário e no conteúdo JSON das alterações.
         * O repository usa três placeholders distintos (:q, :q_u, :q_v) porque o PDO não permite reutilizar o mesmo nome de placeholder na mesma query.
        */ ?>
        <input
            class="filter-input-lg"
            id="f-q"
            type="text"
            name="q"
            placeholder="Viação, usuário ou conteúdo"
            value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="filter-field">
        <label class="filter-label" for="f-acao">Ação</label>
        <?php /*
            <select> em vez de <input type="text">: garante que só valores válidos
            chegam ao servidor e evita erros de digitação ("criado" vs "Criado").
            Pesquise "enumerated values", "constrained input".
        */ ?>
        <select class="filter-input-md" id="f-acao" name="acao">
            <option value="">Todas</option>
            <?php
            /*
             * AcaoHistorico::cases() devolve todos os cases do enum em ordem de declaração.
             * Se um novo valor for adicionado ao enum, ele aparece aqui automaticamente.
             * Antes: array hardcoded ['Criado', 'Editado', 'Excluido'], fácil de esquecer de atualizar.
             * Agora: o enum é a fonte de verdade, a view só itera.
             */ ?>
            <?php foreach (\App\Enums\AcaoHistorico::cases() as $caso): ?>
                <option
                    value="<?= $caso->value ?>"
                    <?= (($filters['acao'] ?? '') === $caso->value) ? 'selected' : '' ?>
                ><?= $caso->value ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-field">
        <label class="filter-label" for="f-de">De</label>
        <input
            id="f-de"
            type="date"
            name="date_from"
            value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="filter-field">
        <label class="filter-label" for="f-ate">Até</label>
        <input
            id="f-ate"
            type="date"
            name="date_to"
            value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="filter-field">
        <?php /* label vazio ocupa o mesmo espaço que as outras labels, alinhando o botão */ ?>
        <span class="filter-label">&nbsp;</span>
        <div class="actions">
            <button type="submit">Filtrar</button>
            <a href="/admin/historico">Limpar</a>
        </div>
    </div>

</form>

<?php if (empty($historico)): ?>
    <p class="muted">Nenhum registro encontrado.</p>
<?php else: ?>

    <p class="small muted"><?= count($historico) ?> registro(s) encontrado(s)</p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Viação</th>
                <th>Usuário</th>
                <th>Ação</th>
                <th>Alterações</th>
                <th>Quando</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($historico as $h): ?>
            <tr>
                <td class="small muted"><?= $h->id ?></td>
                <td><?= htmlspecialchars($h->viacaoLabel(),  ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($h->usuarioLabel(), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($h->acao, ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if (is_array($h->alteracoes)): ?>
                        <?php
                        /* <details> e <summary> são elementos HTML nativos pra expandir/recolher conteúdo.
                        Não precisa de JavaScript, o browser faz isso sozinho.
                        Pesquise "HTML details element" no MDN.
                        */ ?>
                        <details>
                            <summary class="small">Ver alterações</summary>
                            <pre class="diff-pre">Antes:
<?= htmlspecialchars(
    json_encode($h->getBefore(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ENT_QUOTES,
    'UTF-8'
) ?>

Depois:
<?= htmlspecialchars(
    json_encode($h->getAfter(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ENT_QUOTES,
    'UTF-8'
) ?></pre>
                        </details>
                    <?php else: ?>
                        <span class="muted small">---</span>
                    <?php endif; ?>
                </td>
                <td class="small muted"><?= htmlspecialchars($h->criadoEm, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>
