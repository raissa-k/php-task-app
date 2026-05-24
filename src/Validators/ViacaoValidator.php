<?php
// Validator de viação: concentra regras de entrada do form/API

declare(strict_types=1);

namespace App\Validators;

/**
 * Validador simples para criação/edição de viações.
 * Objetivo: tirar lógica de validação dos controllers e fornecer dados sanitizados.
 */
final class ViacaoValidator
{
    /**
     * Valida os dados de input e retorna array com 'errors' e 'data' sanitizado.
     * @param array<string,mixed> $input
     * @return array{errors: list<string>, data: array<string,mixed>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $data = [];

        $nome = trim((string) ($input['nome'] ?? ''));
        if ($nome === '') {
            $errors[] = 'O nome é obrigatório.';
        } elseif (strlen($nome) > 255) {
            $errors[] = 'O nome deve ter no máximo 255 caracteres.';
        }
        $data['nome'] = $nome;

        $cidade = trim((string) ($input['cidade'] ?? ''));
        if ($cidade === '') {
            $errors[] = 'A cidade é obrigatória.';
        } elseif (strlen($cidade) > 255) {
            $errors[] = 'A cidade deve ter no máximo 255 caracteres.';
        }
        $data['cidade'] = $cidade;

        $ativa = isset($input['ativa']) && ((string) $input['ativa'] === '1' || $input['ativa'] === true);
        $data['ativa'] = (bool) $ativa;

        // Placeholder: validação de upload ficará em outro serviço
        $data['logo'] = null;

        return ['errors' => $errors, 'data' => $data];
    }
}
