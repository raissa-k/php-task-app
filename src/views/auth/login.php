<?php
// View de login: usa o layout público e as classes de campo do home.css

declare(strict_types=1);

/** @var array $errors Lista de erros (ex: credenciais inválidas) */
/** @var array $old    Dados do form anterior (só o email, nunca traga a senha!) */
?>

<div class="auth-wrap">
    <div class="card">

        <h1 class="card-title text-center">Entrar na conta</h1>

        <?php if (!empty($errors)): ?>
            <?php
            /*
             * Erros de autenticação são intencionalmente vagos ("E-mail ou senha incorretos").
             * Não diga qual dos dois está errado, isso daria informação a quem tenta adivinhar.
             * Pesquise "user enumeration attack".
            */ ?>
            <div class="auth-error">
                <ul class="error-list">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="search-form" method="POST" action="/login">
            <?= \App\Core\View::csrfField() ?>

            <div class="field">
                <label class="field-label" for="email">E-mail</label>
                <?php /*
                    type="email": o browser valida o formato antes de submeter.
                    Mas nunca confie só na validação do browser, sempre valide no servidor também.
                    Um curl ou Postman ignora qualquer validação client-side.
                */ ?>
                <input
                    class="field-input"
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autocomplete="email"
                    placeholder="seu@email.com"
                >
            </div>

            <div class="field">
                <label class="field-label" for="password">Senha</label>
                <?php /*
                    NUNCA repopule o campo de senha com o valor enviado, é um risco de segurança.
                    autocomplete="current-password" diz pro browser que pode sugerir a senha salva,
                    melhorando a UX sem comprometer segurança.
                */ ?>
                <input
                    class="field-input"
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                >
            </div>

            <button class="btn btn-blue" type="submit">Entrar</button>
        </form>

    </div>
</div>
