<?php
// Service de upload: valida e salva arquivos com segurança básica

declare(strict_types=1);

namespace App\Services;

/**
 * UploadService: trata envio seguro de arquivos (ex: logos)
 * - valida MIME via finfo
 * - verifica tamanho
 * - renomeia arquivo para nome seguro
 * - salva fora do docroot (storage/uploads)
 * Serve de base pra explicar segurança em upload pros estagiários.
 */
final class UploadService
{
    private string $storageDir;
    private int $maxSize;
    private array $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(?string $storageDir = null, int $maxSize = 2 * 1024 * 1024)
    {
        $this->storageDir = $storageDir ?? __DIR__ . '/../../storage/uploads';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        $this->maxSize = $maxSize;
    }

    // Recebe array $_FILES['campo'] e retorna filename salvo ou null
    public function handleUpload(array $file): ?string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
        if ($file['size'] > $this->maxSize) throw new \RuntimeException('Arquivo maior que o permitido.');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);

        if (!in_array($mime, $this->allowedMime, true)) {
            throw new \RuntimeException('Tipo de arquivo não permitido.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $this->storageDir . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Falha ao mover arquivo enviado.');
        }

        return $name;
    }

    public function storagePath(string $filename): string
    {
        return $this->storageDir . DIRECTORY_SEPARATOR . $filename;
    }
}
