<?php
// Validator dos filtros de listagem de usuários (GET params)

declare(strict_types=1);

namespace App\Validators;

/*
 * Mesmo que o único filtro aqui seja um trim() simples, ter um validator dedicado mantém o padrão uniforme.
 * Nenhum controller lê $_GET ou $_POST diretamente e sempre passa por um validator.
 * Isso significa que quando um novo filtro for adicionado, há um lugar óbvio pra colocar a lógica.
 */

final class UsuarioFilterValidator
{
    /**
     * Normaliza os filtros de listagem de usuários.
     *
     * @param array<string, mixed> $input  Geralmente $_GET
     * @return array{q: string}
     */
    public function parse(array $input): array
    {
        return [
            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }
}
