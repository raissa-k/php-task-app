<?php
// Testes de feature do HistoricoRepository: filters

declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use PHPUnit\Framework\TestCase;
use App\Repositories\HistoricoRepository;
use Tests\Support\DatabaseFactory;

/*
 * Aqui testamos o HistoricoRepository diretamente, sem passar pelo Service.
 * Isso permite verificar os filtros dinâmicos de forma precisa, sem a camada extra do Service obscurecer o que está sendo testado.
 *
 * Quando testar o repository diretamente vs testar pelo service?
 * - Repository: quando você quer testar queries, filtros, joins.
 * - Service: quando você quer testar lógica de negócio (ex: transações, before/after).
 * Pesquise "integration test boundaries", "repository pattern testing".
*/

final class HistoricoRepositoryTest extends TestCase
{
    private PDO $pdo;
    private HistoricoRepository $repo;

    protected function setUp(): void
    {
        $this->pdo  = DatabaseFactory::create();
        $this->repo = new HistoricoRepository($this->pdo);
    }

    // Helper para inserir registros diretamente

    private function inserir(int $viacaoId, ?int $usuarioId, string $acao, ?array $before = null, ?array $after = null): int
    {
        return $this->repo->create($viacaoId, $usuarioId, $acao, $before, $after);
    }

    // create()

    public function testCreateRetornaIdInteiro(): void
    {
        $id = $this->inserir(1, null, 'Criado', null, ['nome' => 'X']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateSalvaJsonDeAlteracoes(): void
    {
        $this->inserir(1, null, 'Criado', null, ['nome' => 'Cometa']);

        $row   = $this->pdo->query('SELECT alteracoes FROM viacoes_historico LIMIT 1')->fetch();
        $alter = json_decode($row['alteracoes'], true);

        $this->assertNull($alter['before']);
        $this->assertSame(['nome' => 'Cometa'], $alter['after']);
    }

    // findAll() sem filtros

    public function testFindAllSemFiltrosRetornaTodos(): void
    {
        $this->inserir(1, null, 'Criado');
        $this->inserir(2, null, 'Criado');

        $results = $this->repo->findAll();
        $this->assertCount(2, $results);
    }

    public function testFindAllRetornaObjetosHistorico(): void
    {
        $this->inserir(1, null, 'Criado');

        $results = $this->repo->findAll();
        $this->assertInstanceOf(\App\Models\Historico::class, $results[0]);
    }

    public function testFindAllRetornaBancoVazioComoArrayVazio(): void
    {
        $results = $this->repo->findAll();
        $this->assertSame([], $results);
    }

    // Filtro por acao

    public function testFindAllFiltragemPorAcao(): void
    {
        $this->inserir(1, null, 'Criado');
        $this->inserir(1, null, 'Editado');
        $this->inserir(2, null, 'Excluido');

        $editados = $this->repo->findAll(['acao' => 'Editado']);
        $this->assertCount(1, $editados);
        $this->assertSame('Editado', $editados[0]->acao);
    }

    // Filtro por data

    public function testFindAllFiltragemPorDateFrom(): void
    {
        // Insere um registro com data no passado direto no banco
        $this->pdo->exec("
            INSERT INTO viacoes_historico (viacao_id, usuario_id, acao, alteracoes, criado_em)
            VALUES (1, NULL, 'Criado', '{\"before\":null,\"after\":null}', '2024-01-01 10:00:00')
        ");
        $this->inserir(2, null, 'Criado'); // data atual

        // Filtrando a partir de 2025 não deve trazer o registro de 2024
        $results = $this->repo->findAll(['date_from' => '2025-01-01']);
        foreach ($results as $h) {
            $this->assertGreaterThanOrEqual('2025-01-01 00:00:00', $h->criadoEm);
        }
    }

    // Filtro por busca livre (q)

    public function testFindAllFiltragemPorQ(): void
    {
        $this->inserir(1, null, 'Editado', ['nome' => 'Guanabara'], ['nome' => 'Novo']);
        $this->inserir(2, null, 'Editado', ['nome' => 'Eucatur'],   ['nome' => 'Outro']);

        // Busca pelo texto 'Guanabara' dentro do JSON de alterações
        $results = $this->repo->findAll(['q' => 'Guanabara']);
        $this->assertCount(1, $results);
    }
}
