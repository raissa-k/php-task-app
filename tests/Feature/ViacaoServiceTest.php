<?php
// Testes de feature do ViacaoService com banco de dados real (SQLite em memória)

declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use PHPUnit\Framework\TestCase;
use App\Services\ViacaoService;
use App\Repositories\HistoricoRepository;
use Tests\Support\DatabaseFactory;

/*
 * O que é um teste de feature (integração)?
 * Diferente dos testes unitários, os testes de feature testam a integração entre camadas reais: Service -> Repository -> PDO -> banco de dados.
 * Usamos SQLite em memória (criado pelo DatabaseFactory) em vez de MySQL real.
 * Isso garante isolamento porque cada teste começa com um banco vazio, sem dados de outros testes ou do ambiente de desenvolvimento.
 * Quando usar: sempre que a lógica envolve queries reais, transações ou joins.
 * Pesquise "integration test", "in-memory database testing", "test isolation strategies".
*/

final class ViacaoServiceTest extends TestCase
{
    private PDO $pdo;
    private ViacaoService $service;

    protected function setUp(): void
    {
        // Banco fresco para cada teste sem dados herdados, sem poluição
        $this->pdo     = DatabaseFactory::create();
        $this->service = new ViacaoService($this->pdo);
    }

    // create()

    public function testCreateRetornaIdInteiro(): void
    {
        $id = $this->service->create('Cometa', 'Campinas', true, null);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateInsereRegistroRecuperavelViaFind(): void
    {
        $id     = $this->service->create('Cometa', 'Campinas', true, null);
        $viacao = $this->service->find($id);

        $this->assertNotNull($viacao);
        $this->assertSame('Cometa', $viacao->nome);
        $this->assertSame('Campinas', $viacao->cidade);
        $this->assertTrue($viacao->ativa);
        $this->assertNull($viacao->logo);
    }

    public function testCreateRegistraHistoricoComAcaoCriado(): void
    {
        $id = $this->service->create('Eucatur', 'Curitiba', true, null, usuarioId: 1);

        $historico = $this->historicoParaViacao($id);
        $this->assertCount(1, $historico, 'Esperava exatamente 1 registro de histórico após create');
        $this->assertSame('Criado', $historico[0]['acao']);
    }

    public function testCreateHistoricoTemBeforeNullEAfterPreenchido(): void
    {
        $id        = $this->service->create('Itapemirim', 'Vitória', true, null);
        $historico = $this->historicoParaViacao($id);
        $alter     = json_decode($historico[0]['alteracoes'], true);

        // Ao criar, não havia estado anterior
        $this->assertNull($alter['before']);
        // O after deve conter os dados recém-inseridos
        $this->assertIsArray($alter['after']);
        $this->assertSame('Itapemirim', $alter['after']['nome']);
    }

    // find()

    public function testFindRetornaViacaoExistente(): void
    {
        $id     = $this->service->create('Penha', 'Belo Horizonte', false, null);
        $viacao = $this->service->find($id);

        $this->assertNotNull($viacao);
        $this->assertSame($id, $viacao->id);
    }

    public function testFindRetornaNullParaIdInexistente(): void
    {
        $viacao = $this->service->find(9999);
        $this->assertNull($viacao);
    }

    // all() e active()

    public function testAllRetornaTodasAsViacoes(): void
    {
        $this->service->create('A', 'SP', true,  null);
        $this->service->create('B', 'RJ', false, null);

        $all = $this->service->all();
        $this->assertCount(2, $all);
    }

    public function testActiveRetornaApenasViacoesAtivas(): void
    {
        $this->service->create('Ativa',   'SP', true,  null);
        $this->service->create('Inativa', 'RJ', false, null);

        $active = $this->service->active();
        $this->assertCount(1, $active);
        $this->assertSame('Ativa', $active[0]->nome);
    }

    // update()

    public function testUpdateAlteraOsDadosDaViacao(): void
    {
        $id = $this->service->create('Nome Antigo', 'Cidade Antiga', true, null);
        $this->service->update($id, 'Nome Novo', 'Cidade Nova', false, null);

        $viacao = $this->service->find($id);
        $this->assertSame('Nome Novo', $viacao->nome);
        $this->assertSame('Cidade Nova', $viacao->cidade);
        $this->assertFalse($viacao->ativa);
    }

    public function testUpdateRegistraHistoricoComAcaoEditado(): void
    {
        $id = $this->service->create('Antigo', 'SP', true, null);
        $this->service->update($id, 'Novo', 'RJ', false, null, usuarioId: 2);

        $historico = $this->historicoParaViacao($id);
        // 2 registros: 'Criado' do create + 'Editado' do update
        $this->assertCount(2, $historico);

        $acoes = array_column($historico, 'acao');
        $this->assertContains('Editado', $acoes);
    }

    public function testUpdateHistoricoCapturaDiferencaBeforeAfter(): void
    {
        $id = $this->service->create('Antes', 'SP', true, null);
        $this->service->update($id, 'Depois', 'RJ', false, null);

        // O último registro de histórico é o do update
        $historico = $this->historicoParaViacao($id);
        $editado   = array_values(array_filter($historico, fn($h) => $h['acao'] === 'Editado'));
        $alter     = json_decode($editado[0]['alteracoes'], true);

        $this->assertSame('Antes', $alter['before']['nome']);
        $this->assertSame('Depois', $alter['after']['nome']);
    }

    // delete()

    public function testDeleteRemoveAViacao(): void
    {
        $id = $this->service->create('Pra Excluir', 'SP', true, null);
        $this->service->delete($id);

        $this->assertNull($this->service->find($id));
    }

    public function testDeleteRegistraHistoricoComAcaoExcluido(): void
    {
        $id = $this->service->create('Pra Excluir', 'SP', true, null);
        $this->service->delete($id, usuarioId: 1);

        $historico = $this->historicoParaViacao($id);
        $acoes     = array_column($historico, 'acao');
        $this->assertContains('Excluido', $acoes);
    }

    public function testDeleteHistoricoTemAfterNull(): void
    {
        $id = $this->service->create('X', 'Y', true, null);
        $this->service->delete($id);

        $historico = $this->historicoParaViacao($id);
        $excluido  = array_values(array_filter($historico, fn($h) => $h['acao'] === 'Excluido'));
        $alter     = json_decode($excluido[0]['alteracoes'], true);

        // Após excluir, não há estado "depois"
        $this->assertNull($alter['after']);
        $this->assertIsArray($alter['before']);
    }

    // Transação: rollback em caso de falha

    /*
     * Este teste verifica que a transação funciona corretamente: se o histórico falhar, o INSERT da viação deve ser desfeito (rollback).
     * Para forçar a falha, usamos um "stub": um objeto falso que substitui o HistoricoRepository e faz o método create() lançar uma exception.
     * Diferença entre mock e stub no PHPUnit:
     * createMock()  -> cria um objeto com EXPECTATIVAS: você pode afirmar que certos métodos foram chamados (ou não) e quantas vezes.
     *                  Use quando o comportamento do colaborador é parte do que você está testando.
     * createStub()  -> cria um objeto apenas com COMPORTAMENTO controlado: você define o que os métodos retornam/lançam, mas não afirma quantas vezes foram chamados.
     *                  Use quando você só precisa isolar uma dependência.
     * Aqui usamos createStub() porque não nos importa QUANTAS vezes create() foi chamado, apenas que o sistema trata o erro corretamente.
     * Pesquise "test doubles", "mock vs stub vs spy", "PHPUnit createStub".
    */
    public function testCreateFazRollbackSeFalharAoSalvarHistorico(): void
    {
        $stubRepo = $this->createStub(HistoricoRepository::class);
        $stubRepo->method('create')->willThrowException(new \RuntimeException('Falha simulada'));

        $service = new ViacaoService($this->pdo, $stubRepo);

        try {
            $service->create('Falha', 'Aqui', true, null);
            $this->fail('Deveria ter lançado exception quando o histórico falha');
        } catch (\RuntimeException $e) {
            // A exception foi lançada, agora verificamos que o rollback funcionou:
            // a viação NÃO deve ter sido inserida mesmo que o INSERT tenha chegado a executar
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM viacoes')->fetchColumn();
            $this->assertSame(0, $count, 'Rollback falhou: viação foi persistida mesmo com erro no histórico');
        }
    }

    // Helper privado

    /** Busca todos os registros de histórico de uma viação específica. */
    private function historicoParaViacao(int $viacaoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM viacoes_historico WHERE viacao_id = :id ORDER BY id ASC');
        $stmt->execute(['id' => $viacaoId]);
        return $stmt->fetchAll();
    }
}
