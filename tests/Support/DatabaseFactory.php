<?php
// Helper de teste: cria um banco SQLite em memória com o schema do projeto

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/*
 * Por que SQLite em vez do MySQL real?
 * Os testes de feature precisam de um banco de dados de verdade para testar queries, transações e joins
 * mas conectar ao Docker toda hora pra isso é lento, frágil (precisa do container rodando) e deixa dados sujos.
 * SQLite resolve isso: é um banco sem servidor, cria o banco inteiro em memória RAM e descarta tudo quando a conexão fecha.
 * A diferença mais importante entre SQLite e MySQL neste projeto:
 * - SQLite não suporta ON UPDATE CURRENT_TIMESTAMP (colocamos updated_at como NULL)
 * - Tipos como TINYINT(1), INT UNSIGNED, VARCHAR(n) são aceitos mas ignorados,
 *    - SQLite usa tipagem dinâmica (armazena qualquer valor em qualquer coluna)
 * - AUTO_INCREMENT -> AUTOINCREMENT (ou INTEGER PRIMARY KEY, que já auto-incrementa)
 * Na prática, as queries deste projeto funcionam igual nos dois bancos.
 * Pesquise "SQLite in-memory database", "test isolation", "hermetic tests".
*/

final class DatabaseFactory
{
    public static function create(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Habilita foreign keys no SQLite, por padrão ficam desativadas (aqui só pra demonstração)
        $pdo->exec('PRAGMA foreign_keys = ON');

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nome       TEXT    NOT NULL,
                email      TEXT    NOT NULL UNIQUE,
                senha      TEXT    NOT NULL,
                created_at TEXT    NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS viacoes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nome       TEXT    NOT NULL,
                cidade     TEXT    NOT NULL,
                ativa      INTEGER NOT NULL DEFAULT 1,
                logo       TEXT    NULL,
                created_at TEXT    NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT    NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS viacoes_historico (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                viacao_id  INTEGER NULL,
                usuario_id INTEGER NULL,
                acao       TEXT    NOT NULL,
                alteracoes TEXT    NOT NULL,
                criado_em  TEXT    NOT NULL DEFAULT (datetime('now'))
            )
        ");

        return $pdo;
    }
}
