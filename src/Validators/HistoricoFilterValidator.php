<?php
// Validator dos filtros do histórico de alterações (GET params)

declare(strict_types=1);

namespace App\Validators;

use App\Enums\AcaoHistorico;

/*
 * Assim como ViacaoFilterValidator, este validator usa parse() em vez de validate():
 * filtros inválidos são descartados silenciosamente, nunca mostram erro ao usuário.
 *
 * A lógica de parseDate() e da validação de ação morava espalhada no HistoricoController.
 * Centralizar aqui significa: se o formato de data mudar ou novos valores de ação forem adicionados,
 * há um único lugar pra atualizar. Pesquise "DRY — Don't Repeat Yourself".
 */

final class HistoricoFilterValidator
{
    /**
     * Normaliza os filtros da listagem de histórico.
     * Valores inválidos viram null ou '' (sem filtro), nunca lança exceção.
     *
     * @param array<string, mixed> $input  Geralmente $_GET
     * @return array{viacao_id: int|null, usuario_id: int|null, acao: string, date_from: string|null, date_to: string|null, q: string}
     */
    public function parse(array $input): array
    {
        return [
            /*
             * IDs: cast pra int. Qualquer valor não-numérico ("abc") vira 0.
             * Usamos isset() pra distinguir "não enviado" (null) de "enviado como 0" (inválido).
             * IDs negativos ou zero não existem no banco, então tratamos como "sem filtro".
             */
            'viacao_id'  => isset($input['viacao_id'])  && (int) $input['viacao_id']  > 0 ? (int) $input['viacao_id']  : null,
            'usuario_id' => isset($input['usuario_id']) && (int) $input['usuario_id'] > 0 ? (int) $input['usuario_id'] : null,

            /*
             * Antes: in_array() manual com ACOES_VALIDAS = ['Criado', 'Editado', 'Excluido']
             * Agora: AcaoHistorico::tryFrom() tendo o enum como fonte de verdade dos valores válidos.
             *
             * A diferença: se um novo valor de ação for adicionado, basta adicionar ao enum que a allowlist aqui se atualiza automaticamente.
             * Com in_array() e a constante separados, seria fácil atualizar um e esquecer do outro.
             *
             * tryFrom() retorna null para qualquer valor não reconhecido, exatamente o comportamento de "sem filtro" que queremos.
             * Depois extraímos ->value para o array (string pra o banco).
             */
            'acao' => (AcaoHistorico::tryFrom(trim((string) ($input['acao'] ?? ''))) ?? null)?->value ?? '',

            /*
             * Datas: parseDate() valida o formato E os valores antes de deixar passar.
             * Sem isso, "2025-99-99" ou "ontem" chegam no SQL e o MySQL trata como NULL, 0000-00-00, etc.
             */
            'date_from' => self::parseDate($input['date_from'] ?? ''),
            'date_to'   => self::parseDate($input['date_to']   ?? ''),

            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }

    /**
     * Valida e normaliza uma string de data no formato Y-m-d.
     * Retorna a string original se válida, null se inválida ou vazia.
     *
     * Por que não usar strtotime()?
     * strtotime() é permissivo demais: aceita "ontem", "next friday", "01/15/2025" — tudo válido pra ele.
     * DateTime::createFromFormat() é estrito: exige exatamente o padrão informado, nada a mais.
     *
     * Por que o segundo check ($d->format('Y-m-d') === $value)?
     * O PHP às vezes "corrige" datas inválidas silenciosamente:
     *   createFromFormat('Y-m-d', '2025-02-30') retorna um objeto, mas representa 2025-03-02.
     * Sem esse check, a data passaria como válida quando na verdade foi normalizada.
     * Reformatar e comparar com o original é a forma de detectar esse caso.
     */
    private static function parseDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : null;
    }
}
