<?php

declare(strict_types=1);

/** Le variavel de ambiente com valor padrao. */
function env(string $key, ?string $default = null): string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default ?? '';
    }

    return (string) $value;
}

/** Retorna uma instancia compartilhada de PDO.
 * @throws PDOException Quando falha ao conectar no banco.
 */
function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', 'db');
    $db = env('DB_NAME', 'tasks');
    $user = env('DB_USER', 'app');
    $pass = env('DB_PASSWORD', 'app123');
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
