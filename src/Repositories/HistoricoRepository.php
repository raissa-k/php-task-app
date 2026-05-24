<?php
// Repository do histórico: isola todo SQL de leitura e escrita do histórico de alterações

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Historico;
use PDO;

/*
 * Por que usar prepared statements em vez de concatenar strings SQL?
 * Concatenar input do usuário diretamente na query abre brecha pra SQL injection:
 * $sql = "SELECT * FROM historico WHERE acao = '" . $acao . "'";
    // Se $acao for  `' OR '1'='1`  retorna todos os registros!
 * Com prepare() + execute([...]), o banco de dados trata os valores como dados puros, nunca como parte da estrutura SQL.
 * Pesquise "SQL injection", "prepared statements", "Bobby Tables" (sim, baseado na comic xkcd mas também tem um site legal com dicas).
*/

class HistoricoRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \getPdo();
    }

    /**
     * Insere um registro de histórico com o estado antes e depois da alteração.
     * Retorna o ID do registro criado.
     *
     * Por que JSON_UNESCAPED_UNICODE?
     * Sem essa flag, json_encode() converte "ã" (U+00E3) -> "ã" ("\u00e3"), "ç" (U+00E7) -> "ç" ("\u00e7"), etc.
     * A string fica maior e ilegível na coluna do banco. Com a flag, os caracteres ficam como UTF-8 legível.
     */
    public function create(int $viacaoId, ?int $usuarioId, string $acao, ?array $before = null, ?array $after = null): int
    {
        $alteracoes = json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            'INSERT INTO viacoes_historico (viacao_id, usuario_id, acao, alteracoes)
             VALUES (:viacao_id, :usuario_id, :acao, :alteracoes)'
        );
        $stmt->execute([
            'viacao_id'  => $viacaoId,
            'usuario_id' => $usuarioId,
            'acao'       => $acao,
            'alteracoes' => $alteracoes,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Busca um único registro pelo ID, trazendo nome de usuário e de viação via JOIN. */
    public function findById(int $id): ?Historico
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.*, u.nome AS usuario_nome, v.nome AS viacao_nome
             FROM viacoes_historico h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             LEFT JOIN viacoes v  ON v.id = h.viacao_id
             WHERE h.id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        return is_array($row) ? Historico::fromRow($row) : null;
    }

    /**
     * Busca registros de histórico com filtros opcionais.
     *
     * Filtros aceitos:
     * viacao_id  - filtra por viação específica
     * usuario_id - filtra por usuário
     * acao       - "Criado", "Editado" ou "Excluido"
     * date_from  - data inicial no formato Y-m-d
     * date_to    - data final no formato Y-m-d
     * q          - busca livre dentro do JSON de alterações
     *
     * @return list<Historico>
     */
    public function findAll(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);

        $sql = "SELECT h.*, u.nome AS usuario_nome, v.nome AS viacao_nome
                FROM viacoes_historico h
                LEFT JOIN usuarios u ON u.id = h.usuario_id
                LEFT JOIN viacoes v  ON v.id = h.viacao_id
                {$whereSql}
                ORDER BY h.criado_em DESC";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }

        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = Historico::fromRow($row);
        }
        return $out;
    }

    /**
     * Monta a cláusula WHERE e os parâmetros de bind a partir dos filtros ativos.
     *
     * Por que extrair esse método?
     * Além de legibilidade, futuros métodos poderiam precisar do mesmo WHERE.
     * Sem buildWhere(), teríamos a lógica duplicada, e qualquer bug ou novo filtro precisaria ser corrigido em vários lugares.
     *
     * Essa abordagem (acumular cláusulas num array e juntar com implode()) é uma forma mais segura de montar queries dinâmicas.
     * Nunca concatene o valor diretamente na string, sempre use um placeholder (:nome) e registre o valor em $params.
     *
     * Pesquise "dynamic SQL", "query builder pattern".
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $wheres = [];
        $params = [];

        if (!empty($filters['acao']))       {
            $wheres[] = 'h.acao = :acao';
            $params['acao']       = $filters['acao'];
        }
        if (!empty($filters['date_from']))  {
            $wheres[] = 'h.criado_em >= :date_from';
            // Adiciona hora 00:00:00 pra incluir o dia inteiro a partir do começo
            $params['date_from']  = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to']))    {
            $wheres[] = 'h.criado_em <= :date_to';
            // 23:59:59 pra incluir tudo até o final do dia
            $params['date_to']    = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['q']))          {
            /* LIKE com % nos dois lados = busca em qualquer posição da string.
            Aqui buscamos dentro do JSON de alterações, útil pra encontrar uma viação pelo nome mesmo sem saber o ID.
            Atenção: LIKE '%texto%' não usa índice, faz varredura completa. Pra volumes grandes, considere FULLTEXT INDEX ou busca dedicada.
            Pesquise "MySQL FULLTEXT search", "LIKE performance".
            */
            $wheres[] = 'h.alteracoes LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

        return [$whereSql, $params];
    }
}
