<?php
// Controller de upload: serve arquivos salvos fora do docroot com segurança

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UploadService;

/*
 * Por que os uploads ficam fora do docroot?
 * Se os arquivos ficassem em public/uploads/, qualquer um poderia acessá-los diretamente pela URL sem passar pelo PHP.
 * Pior: se alguém conseguisse fazer upload de um arquivo .php, poderia executar código no servidor.
 * Guardando os arquivos fora do docroot (em storage/uploads/), o Apache não consegue servi-los diretamente.
 * O único jeito de acessar um arquivo é por este controller, que:
 * 1. Valida o nome do arquivo (sem traversal)
 * 2. Verifica que o arquivo existe
 * 3. Detecta o MIME type real e define o Content-Type correto
 * 4. Serve o conteúdo via readfile()
 * Pesquise "directory traversal attack" e "OWASP File Upload".
 */

final class UploadController
{
    private UploadService $upload;

    public function __construct(?UploadService $upload = null)
    {
        $this->upload = $upload ?? new UploadService();
    }

    public function serve(string $filename): void
    {
        /*
         * Proteção contra Path Traversal:
         * Um atacante poderia tentar acessar /uploads/../../../etc/passwd passando '..' no nome do arquivo pra navegar acima do diretório.
         * basename() retorna só o componente final do caminho e remove qualquer sequência de diretórios.
         * Se o filename vier com '..' ou '/', basename() os elimina. Depois comparamos com o original, se mudou é suspeito.
         * Exemplos:
         * basename('../etc/passwd')  ->  'passwd'     ≠ '../etc/passwd' -> bloqueado
         * basename('abc123.jpg')     ->  'abc123.jpg' = 'abc123.jpg'    -> permitido
         *
         * Por que não usar strpos($filename, '..')?
         * Essa verificação é frágil e depende de você imaginar todos os padrões de ataque possíveis.
         * basename() atua no resultado, não no padrão.
        */
        if (basename($filename) !== $filename) {
            http_response_code(400);
            echo 'Nome de arquivo inválido.';
            return;
        }

        $path = $this->upload->storagePath($filename);

        if (!is_file($path)) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        /*
         * finfo detecta o MIME type pelo conteúdo real do arquivo e não pela extensão.
         * Isso é importante pra segurança: se setarmos o Content-Type errado, o browser pode interpretar o arquivo de forma inesperada (ex: executar HTML).
         * Pesquise "content sniffing" e "MIME type security".
        */
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, $path);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        // Cache por 1 hora, logos não mudam com frequência
        header('Cache-Control: public, max-age=3600');

        readfile($path);
    }
}
