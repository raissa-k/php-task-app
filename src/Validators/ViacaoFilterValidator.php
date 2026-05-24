<?php
// Validator dos filtros de listagem de viações (GET params)

declare(strict_types=1);

namespace App\Validators;

/*
 * Por que "FilterValidator" e não só "Validator"?
 * Este arquivo lida com filtros de busca vindos de $_GET, não com dados de formulário (POST).
 * A diferença principal: filtros não vão falhar com mensagem de erro pro usuário.
 * Um filtro inválido (ex: "ativa=xyz") simplesmente é ignorado e vira o padrão seguro.
 * Por isso usamos parse() em vez de validate(), o contrato é diferente:
 * validate() -> pode retornar erros, lança ValidationException via Request
 * parse()    -> sempre retorna dados limpos
 */

final class ViacaoFilterValidator
{
    /**
     * Normaliza os filtros de listagem de viações.
     * Valores inválidos viram o padrão seguro, não exception.
     *
     * @param array<string, mixed> $input  Geralmente $_GET
     * @return array{q: string, ativa: bool|null}
     */
    public function parse(array $input): array
    {
        $q = trim((string) ($input['q'] ?? ''));

        /*
         * 'ativa' vem como string do $_GET: '1' (ativas), '0' (inativas), ou '' (todas).
         * Cast via (int) garante que qualquer string não-numérica vira 0 (false) em vez de true.
         * Exemplo: 'ativa=sim' -> (int)'sim' = 0 -> false.
         * Sem o cast, (bool)'sim' = true, o que está errado.
         * null significa "sem filtro" (mostrar todas).
         */
        $ativaRaw = (string) ($input['ativa'] ?? '');
        $ativa    = $ativaRaw !== '' ? (bool) (int) $ativaRaw : null;

        return [
            'q'    => $q,
            'ativa' => $ativa,
        ];
    }
}
