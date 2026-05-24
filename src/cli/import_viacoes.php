<?php
// Job CLI de import: valida e insere viações via JSON

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../database/db.php';

use App\Services\ViacaoService;
use App\Validators\ViacaoValidator;

$argv_file = $argv[1] ?? null;
if ($argv_file === null) {
    echo "Uso: 'php import_viacoes.php nome_do_arquivo.json'" . PHP_EOL;
    exit(1);
}

if (!is_file($argv_file)) {
    echo "Arquivo não encontrado: {$argv_file}\n";
    exit(1);
}

$raw = file_get_contents($argv_file);
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo "JSON Inválido" . PHP_EOL;
    exit(1);
}

$pdo = getPdo();
$service = new ViacaoService($pdo);
$validator = new ViacaoValidator();

$created = 0;
$skipped = 0;
$errors = [];

foreach ($data as $i => $item) {
    $result = $validator->validate(is_array($item) ? $item : []);
    if (!empty($result['errors'])) {
        $skipped++;
        $errors[$i] = $result['errors'];
        continue;
    }

    $d = $result['data'];
    $service->create($d['nome'], $d['cidade'], $d['ativa'], $d['logo'] ?? null);
    $created++;
}

echo "Importação completa: criado={$created}, skipped={$skipped}" . PHP_EOL;
if (!empty($errors)) {
    echo "Erros:" . PHP_EOL . print_r($errors, true) . PHP_EOL;
}
