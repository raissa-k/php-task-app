<?php
// Script de seed: insere dados iniciais de desenvolvimento (usuários, viações e logs de demo)
// Executar via: docker compose exec viacoes_php_demo_app php src/cli/seed.php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

/*
 * Por que o seed fica aqui e não no init.sql?
 * O init.sql roda uma única vez quando o banco é criado, como SQL puro. Não tem como chamar password_hash() ou lógica PHP a partir dele.
 * password_hash() gera um hash bcrypt com um salt aleatório. A cada chamada o resultado é diferente, mas todos os hashes gerados são válidos com password_verify().
 * Além disso, centralizar todos os dados aqui (usuários + viações + logs) torna o seed a fonte única de verdade pra dados de desenvolvimento.
 * Pesquisar: "bcrypt", "password_hash PHP", "database seed vs migration".
*/

$pdo = getPdo();

echo PHP_EOL . "--- Usuários ---" . PHP_EOL;

$usuarios = [
    [
        'nome'  => 'Admin',
        'email' => 'admin@admin.com',
        /* Pesquise "bcrypt cost factor", "OWASP password storage cheat sheet". */
        'senha' => password_hash('admin123', PASSWORD_BCRYPT),
    ],
];

/*
 * INSERT IGNORE: se o email já existir (violação da UNIQUE key), o banco ignora o INSERT em vez de lançar erro.
 * Sem o IGNORE, rodar este script duas vezes daria "Duplicate entry".
 * Com o IGNORE, é seguro rodar quantas vezes quiser (idempotente).
 * rowCount() retorna 0 quando o registro foi ignorado.
 * Pesquise "INSERT IGNORE MySQL", "idempotent operation".
*/
$stmtU = $pdo->prepare('INSERT IGNORE INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)');

foreach ($usuarios as $u) {
    $stmtU->execute($u);
    if ($stmtU->rowCount() > 0) {
        echo "Criado:          {$u['email']}\n";
    } else {
        echo "Já existe:       {$u['email']}\n";
    }
}

// Pega o ID do admin pra atribuir as ações de historico a um usuário real
$adminRow = $pdo->query("SELECT id FROM usuarios WHERE email = 'admin@admin.com' LIMIT 1")->fetch();
$adminId  = $adminRow !== false ? (int) $adminRow['id'] : null;

echo PHP_EOL . "--- Viações ---" . PHP_EOL;

/*
 * Por que verificar pelo nome antes de inserir?
 * A tabela viacoes não tem UNIQUE no nome, por isso não dá pra usar INSERT IGNORE como fazemos com usuários.
 * Em vez disso, verificamos se já existe uma viação com aquele nome antes de inserir.
 * Isso torna o seed idempotente sem precisar de constraint extra no banco de desenvolvimento que pode prejudicar lógica de produção.
 * Pesquise "idempotent seed", "database fixtures vs factories".
*/

$viacoes = [
    ['nome' => 'Expresso Guanabara', 'cidade' => 'Rio de Janeiro', 'ativa' => 1],
    ['nome' => 'Eucatur',            'cidade' => 'Curitiba',       'ativa' => 1],
    ['nome' => 'Reunidas Paulista',  'cidade' => 'São Paulo',      'ativa' => 1],
    ['nome' => 'Cometa',             'cidade' => 'Campinas',       'ativa' => 1],
    ['nome' => 'Itapemirim',         'cidade' => 'Vitória',        'ativa' => 1],
    ['nome' => 'Real Expresso',      'cidade' => 'Brasília',       'ativa' => 1],
    ['nome' => 'Penha',              'cidade' => 'Belo Horizonte', 'ativa' => 0],
];

$checkV  = $pdo->prepare('SELECT id FROM viacoes WHERE nome = :nome LIMIT 1');
$insertV = $pdo->prepare('INSERT INTO viacoes (nome, cidade, ativa) VALUES (:nome, :cidade, :ativa)');

$viacaoIds    = []; // nome => id (todos, novos e existentes)
$viacaoNovos  = []; // nome => id (só os que o seed acabou de criar)

foreach ($viacoes as $v) {
    $checkV->execute(['nome' => $v['nome']]);
    $row = $checkV->fetch();

    if ($row === false) {
        $insertV->execute($v);
        $id = (int) $pdo->lastInsertId();
        $viacaoIds[$v['nome']]   = $id;
        $viacaoNovos[$v['nome']] = $id;
        echo "Criada:          {$v['nome']} ({$v['cidade']})\n";
    } else {
        $id = (int) $row['id'];
        $viacaoIds[$v['nome']] = $id;
        echo "Já existe:       {$v['nome']}\n";
    }
}

echo PHP_EOL . "--- Histórico ---" . PHP_EOL;

/*
 * Por que seedar histórico?
 * Para poder testar visualizações e filtros logo no início do desenvolvimento. Não precisa, mas está aqui para facilitar.
 * Em frameworks como Laravel, isso seria um "seeder" dedicado, aqui está junto pra simplicidade.
 * Pesquise "database seeders", "fixtures vs factories", "faker library".
*/

$insertH = $pdo->prepare(
    'INSERT INTO viacoes_historico (viacao_id, usuario_id, acao, alteracoes)
     VALUES (:viacao_id, :usuario_id, :acao, :alteracoes)'
);

// "Criado" para cada viação que o seed acabou de inserir nesta execução
foreach ($viacaoNovos as $nome => $id) {
    /*
     * Busca o estado atual da viação pra registrar como "after".
     * O "before" é null porque não existia antes, assim como faz o ViacaoService.
     * json_encode com JSON_UNESCAPED_UNICODE mantém caracteres como ã, ç legíveis no banco, sem converte "ã" (U+00E3) para "ã" ("\u00e3").
    */
    $vRow = $pdo->prepare('SELECT nome, cidade, ativa, logo FROM viacoes WHERE id = :id');
    $vRow->execute(['id' => $id]);
    $after = $vRow->fetch(PDO::FETCH_ASSOC);

    $insertH->execute([
        'viacao_id'  => $id,
        'usuario_id' => $adminId,
        'acao'       => 'Criado',
        'alteracoes' => json_encode(['before' => null, 'after' => $after], JSON_UNESCAPED_UNICODE),
    ]);
    echo "Log Criado:      {$nome}\n";
}

/*
 * Logs de "Editado" de demo: mostram como um diff parcial fica no JSON.
 * O ViacaoService só guarda os campos que mudaram (diffRows), por isso os before/after aqui têm só as chaves alteradas, não o registro inteiro.
*/
$editsDemoReais = [
    'Cometa' => [
        'before' => ['nome' => 'Expresso Cometa'],
        'after'  => ['nome' => 'Cometa'],
    ],
    'Penha' => [
        'before' => ['ativa' => '1'],
        'after'  => ['ativa' => '0'],
    ],
];

foreach ($editsDemoReais as $nome => $diff) {
    if (!isset($viacaoNovos[$nome])) {
        // Só cria log de demo se a viação foi criada agora, evita duplicatas se já existia a viação antes de rodar o seeder
        continue;
    }

    $insertH->execute([
        'viacao_id'  => $viacaoNovos[$nome],
        'usuario_id' => $adminId,
        'acao'       => 'Editado',
        'alteracoes' => json_encode($diff, JSON_UNESCAPED_UNICODE),
    ]);
    echo "Log Editado:     {$nome}\n";
}

echo PHP_EOL . "Seed concluído." . PHP_EOL;
