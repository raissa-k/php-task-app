<?php
// Conexão PDO centralizada: único lugar do projeto que sabe como conectar no banco

declare(strict_types=1);

/**
 * Lê uma variável de ambiente com valor padrão.
 * Usamos variáveis de ambiente pra não colocar senha no código.
 * No Docker, elas vêm do docker-compose.yml. Em produção, do servidor ou .env.
 */
function env(string $key, ?string $default = null): string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default ?? '';
    }

    return (string) $value;
}

/**
 * Retorna uma instância compartilhada de PDO (singleton).
 *
 * Por que singleton?
 * Abrir uma conexão com o banco tem custo. Criar várias conexões pra um mesmo request é desperdício.
 * Com static $pdo, a conexão é criada uma vez e reutilizada em todas as chamadas durante o mesmo request PHP.
 *
 * Pesquise "PHP PDO connection pooling" e "singleton pattern".
 *
 * @throws \PDOException quando a conexão falha (host errado, senha inválida, etc.)
 */
function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = env('DB_HOST',     'viacoes_php_demo_db'); // default costuma ser interessante para DBs locais
    $db      = env('DB_NAME',     'viacoes_demo');
    $user    = env('DB_USER',     'viacoes_user');
    $pass    = env('DB_PASSWORD', 'viacoes_pass');
    $charset = 'utf8mb4';

    /* utf8mb4: suporte completo a Unicode. O "utf8" do MySQL é limitado a 3 bytes e não suporta todos os caracteres.*/
    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

    $options = [
        /*
         * ERRMODE_EXCEPTION: faz o PDO lançar exceções em vez de retornar false silenciosamente.
         * Sem isso, um erro de SQL seria ignorado e o bug seria difícil de encontrar.
         * Pesquise "PDO error modes".
         */
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        /*
         * FETCH_ASSOC: fetchAll() retorna arrays com chaves string (nome da coluna), não arrays com índices numéricos.
         * Muito mais legível: $row['nome'] > $row[1].
         */
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
