<?php
// Script simples de migração: executa init.sql e recria tabelas

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/db.php';

try {
    $pdo = getPdo();
    $sql = file_get_contents(__DIR__ . '/init.sql');
    if ($sql === false) {
        throw new RuntimeException('init.sql não encontrado.');
    }

    // Executa múltiplos statements.
    $pdo->exec($sql);
    echo "Migrações aplicadas com sucesso.\n";
} catch (Throwable $e) {
    echo 'Erro: ' . $e->getMessage() . "\n";
    exit(1);
}
