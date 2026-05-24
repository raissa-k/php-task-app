<?php
// View da home pública: hero + grid de viações ativas

declare(strict_types=1);

/** @var list<\App\Models\Viacao> $viacoes Lista de viações ativas vinda do HomeController */
?>

<?php /* HERO SECTION */ ?>
<section class="hero">
    <div class="container hero-inner">

        <?php /* Lado esquerdo: cartão de busca */ ?>
        <div class="card">
            <h2 class="card-title">Buscar passagem</h2>

            <?php /*
                Esse form é decorativo nesse demo (não tem backend de busca ainda).
                Num sistema real, você criaria um SearchController com filtros de cidade/data.
                Por ora serve pra mostrar como montar um form HTML semântico e acessível.

                Dica: sempre use <label> associado ao input correto (atributo "for" = "id" do input).
                Isso melhora a acessibilidade e a UX (clicar no label foca o campo).
            */ ?>
            <form class="search-form" action="/" method="GET">
                <div class="field">
                    <label class="field-label" for="origem">Origem</label>
                    <input
                        class="field-input"
                        type="text"
                        id="origem"
                        name="origem"
                        placeholder="De onde você vai sair?"
                        value="<?= htmlspecialchars($_GET['origem'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="field">
                    <label class="field-label" for="destino">Destino</label>
                    <input
                        class="field-input"
                        type="text"
                        id="destino"
                        name="destino"
                        placeholder="Para onde você vai?"
                        value="<?= htmlspecialchars($_GET['destino'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="data">Data</label>
                        <input
                            class="field-input"
                            type="date"
                            id="data"
                            name="data"
                            value="<?= htmlspecialchars($_GET['data'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="field">
                        <label class="field-label" for="passageiros">Passageiros</label>
                        <select class="field-input" id="passageiros" name="passageiros">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= (($_GET['passageiros'] ?? '1') == $i) ? 'selected' : '' ?>>
                                    <?= $i ?> <?= $i === 1 ? 'passageiro' : 'passageiros' ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <button class="btn btn-blue" type="submit">Buscar passagem</button>
            </form>
        </div>

        <?php /* Lado direito: texto de chamada */ ?>
        <div class="flex flex-col gap-sm">
            <p class="hero-eyebrow">🚌 Encontre sua viagem</p>
            <h1 class="hero-title font-black text-white">VAI DE ÔNIBUS</h1>
            <p class="hero-subtitle">
                As melhores viações do Brasil em um só lugar.
            </p>
        </div>

    </div>
</section>

<?php /* SEÇÃO DE DIFERENCIAIS */ ?>
<section class="diferenciais">
    <div class="container flex justify-center flex-wrap gap-xl">
        <div class="diferencial">
            <span class="diferencial-icon">🛡️</span>
            <div>
                <strong>Viagens seguras</strong>
                <p class="text-muted text-sm">Só viações verificadas e cadastradas</p>
            </div>
        </div>
        <div class="diferencial">
            <span class="diferencial-icon">💳</span>
            <div>
                <strong>Pagamento fácil</strong>
                <p class="text-muted text-sm">Pix, cartão, boleto</p>
            </div>
        </div>
        <div class="diferencial">
            <span class="diferencial-icon">↩️</span>
            <div>
                <strong>Cancelamento</strong>
                <p class="text-muted text-sm">Política clara por viação</p>
            </div>
        </div>
    </div>
</section>

<?php /* SEÇÃO DE VIAÇÕES */ ?>
<section class="section section-alt">
    <div class="container">
        <h2 class="text-xl font-bold mb-sm">Viações de Ônibus</h2>
        <p class="text-muted mb-lg">
            <?= count($viacoes) ?> viação<?= count($viacoes) !== 1 ? 'ões' : '' ?> disponível<?= count($viacoes) !== 1 ? 'eis' : '' ?> no sistema
        </p>

        <?php if (empty($viacoes)): ?>
            <?php /* Tratando o estado vazio pra não deixar a tela em branco */ ?>
            <div class="empty-state">
                <p>Nenhuma viação cadastrada ainda.</p>
                <a href="/login">Entrar</a> pra cadastrar a primeira.
            </div>
        <?php else: ?>
            <?php
            /*
             * Grid de cards: cada card mostra o logo (se tiver) ou o nome da viação.
             * $v é um objeto \App\Models\Viacao: acesse $v->nome, $v->cidade, etc.
             * Nunca echo direto: sempre use htmlspecialchars() pra evitar XSS.
             * .grid-auto = grid responsivo com auto-fill (veja home.css)
            */ ?>
            <div class="grid-auto">
                <?php foreach ($viacoes as $v): ?>
                    <div class="viacao-card">
                        <div class="viacao-logo">
                            <?php if ($v->logo !== null): ?>
                                <?php
                                /*
                                 * Logo existe: mostra a imagem.
                                 * O alt text é importante pra acessibilidade e SEO.
                                */ ?>
                                <img
                                    src="/uploads/<?= htmlspecialchars($v->logo, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Logo da <?= htmlspecialchars($v->nome, ENT_QUOTES, 'UTF-8') ?>"
                                >
                            <?php else: ?>
                                <?php
                                /*
                                 * Sem logo: mostra as iniciais.
                                 * mb_substr() respeita caracteres UTF-8 (acentos, cedilha, etc.)
                                 * strtoupper() é seguro aqui porque as iniciais são ASCII.
                                */ ?>
                                <div class="viacao-initials">
                                    <?= htmlspecialchars(
                                        strtoupper(mb_substr($v->nome, 0, 2, 'UTF-8')),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col gap-sm w-full">
                            <strong class="viacao-nome">
                                <?= htmlspecialchars($v->nome, ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                            <span class="viacao-cidade">
                                📍 <?= htmlspecialchars($v->cidade, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
