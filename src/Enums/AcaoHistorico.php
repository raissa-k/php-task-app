<?php
// Enum das ações possíveis no histórico de viações.

declare(strict_types=1);

namespace App\Enums;

/*
 * O que é um enum?
 * Um enum (enumeração) é um tipo que representa um conjunto fixo de valores possíveis.
 * Antes dos enums (PHP < 8.1), usávamos constantes ou strings avulsas:
 *
 *   // Antes: string hardcoded, sem garantia de valor válido
 *   $this->historicoRepo->create($id, $userId, 'Criado', ...);
 *   $this->historicoRepo->create($id, $userId, 'criado', ...);   // casing errado
 *   $this->historicoRepo->create($id, $userId, 'Deletaixon', ...); // valor inválido
 *
 *   // Com enum: o PHP impede valores inválidos em tempo de execução
 *   $this->historicoRepo->create($id, $userId, AcaoHistorico::Criado->value, ...);
 *   // AcaoHistorico::Deletaixon -> PHP Fatal Error imediato: case não existe
 *
 * Por que "backed enum" (enum com ': string')?
 * Um backed enum tem um valor primitivo associado a cada case.
 * Isso permite:
 *   - Salvar no banco:   AcaoHistorico::Criado->value  == 'Criado'
 *   - Ler do input:      AcaoHistorico::tryFrom('Criado') == AcaoHistorico::Criado
 *   - Comparar por tipo: $acao === AcaoHistorico::Criado
 *
 * Pesquise "PHP 8.1 enums", "backed enum PHP".
 */

enum AcaoHistorico: string
{
    case Criado   = 'Criado';
    case Editado  = 'Editado';
    case Excluido = 'Excluido';

    /*
     * tryFrom() vs from():
     * from('Criado')    -> AcaoHistorico::Criado (ou exception se não existir)
     * tryFrom('Criado') -> AcaoHistorico::Criado (ou null se não existir, sem exception)
     *
     * Para input do usuário, é bom usar tryFrom() já que o usuário pode enviar qualquer string.
     * tryFrom() é fornecido automaticamente pelo PHP para todos os backed enums.
     */
}
