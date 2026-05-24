<?php
// Testes unitários do Model Historico

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Historico;

/*
 * O ponto mais delicado do Historico é o campo 'alteracoes': é uma string JSON no banco que precisa ser decodificada em um array PHP.
 * getBefore() e getAfter() extraem partes específicas desse JSON.
 * Se o JSON estiver malformado ou incompleto, não deve explodir e sim retornar null.
 * Testar esses "edge cases" (casos extremos) é o objetivo desta classe de testes.
 * Pesquise "edge case testing", "defensive programming".
*/

final class HistoricoModelTest extends TestCase
{
    // Helper: monta uma linha de banco com o JSON de alterações que você passar
    private function makeRow(string $alteracoes, string $acao = 'Editado'): array
    {
        return [
            'id'           => '1',
            'viacao_id'    => '10',
            'usuario_id'   => '5',
            'usuario_nome' => 'Admin',
            'acao'         => $acao,
            'alteracoes'   => $alteracoes,
            'criado_em'    => '2025-01-01 10:00:00',
        ];
    }

    // getBefore()

    public function testGetBeforeRetornaArrayQuandoExiste(): void
    {
        $json = json_encode(['before' => ['nome' => 'Antigo'], 'after' => ['nome' => 'Novo']]);
        $h    = Historico::fromRow($this->makeRow($json));
        $this->assertSame(['nome' => 'Antigo'], $h->getBefore());
    }

    public function testGetBeforeNullNoRegistroCriado(): void
    {
        // Ao criar: não havia estado anterior, before é null por definição
        $json = json_encode(['before' => null, 'after' => ['nome' => 'Novo']]);
        $h    = Historico::fromRow($this->makeRow($json, 'Criado'));
        $this->assertNull($h->getBefore());
    }

    // getAfter()

    public function testGetAfterRetornaArrayQuandoExiste(): void
    {
        $json = json_encode(['before' => ['nome' => 'Antigo'], 'after' => ['nome' => 'Novo']]);
        $h    = Historico::fromRow($this->makeRow($json));
        $this->assertSame(['nome' => 'Novo'], $h->getAfter());
    }

    public function testGetAfterNullNoRegistroExcluido(): void
    {
        // Ao excluir: o objeto não existe mais, after é null por definição
        $json = json_encode(['before' => ['nome' => 'X'], 'after' => null]);
        $h    = Historico::fromRow($this->makeRow($json, 'Excluido'));
        $this->assertNull($h->getAfter());
    }

    // Casos extremos

    public function testJsonInvalidoNaoQuebraFromRow(): void
    {
        // Programação defensiva: o model não deve explodir com dados corrompidos
        $h = Historico::fromRow($this->makeRow('not-valid-json'));
        $this->assertNull($h->getBefore());
        $this->assertNull($h->getAfter());
    }

    public function testAlteracoesVaziasTratadoComoNull(): void
    {
        $row               = $this->makeRow('{"before":null,"after":null}');
        $row['alteracoes'] = '';
        $h                 = Historico::fromRow($row);
        $this->assertNull($h->getBefore());
        $this->assertNull($h->getAfter());
    }

    public function testJsonSemChaveBeforeNaoQuebraGetBefore(): void
    {
        // JSON válido mas sem a chave 'before': não deve lançar exceção
        $json = json_encode(['after' => ['nome' => 'X']]);
        $h    = Historico::fromRow($this->makeRow($json));
        $this->assertNull($h->getBefore());
    }

    public function testJsonSemChaveAfterNaoQuebraGetAfter(): void
    {
        $json = json_encode(['before' => ['nome' => 'X']]);
        $h    = Historico::fromRow($this->makeRow($json));
        $this->assertNull($h->getAfter());
    }

    // Outros campos

    public function testAcaoEhPreservada(): void
    {
        $h = Historico::fromRow($this->makeRow('{"before":null,"after":null}', 'Criado'));
        $this->assertSame('Criado', $h->acao);
    }

    public function testUsuarioNomeEhPreservado(): void
    {
        $h = Historico::fromRow($this->makeRow('{"before":null,"after":null}'));
        $this->assertSame('Admin', $h->usuarioNome);
    }

    public function testUsuarioIdNullEhPermitido(): void
    {
        // Operações feitas via CLI (seed, import) não têm usuário logado
        $row               = $this->makeRow('{"before":null,"after":null}');
        $row['usuario_id'] = null;
        $h                 = Historico::fromRow($row);
        $this->assertNull($h->usuarioId);
    }

    public function testViacaoIdNullEhPermitido(): void
    {
        // Após excluir uma viação, o viacao_id pode ficar órfão
        $row              = $this->makeRow('{"before":null,"after":null}');
        $row['viacao_id'] = null;
        $h                = Historico::fromRow($row);
        $this->assertNull($h->viacaoId);
    }
}
