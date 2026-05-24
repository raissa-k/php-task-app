<?php
// Exception usada quando validação falha; carrega lista de erros

declare(strict_types=1);

namespace App\Http;

// Exception lançada quando validação falha; carregando array de erros
final class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(string $message = 'Falha na validação', array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
