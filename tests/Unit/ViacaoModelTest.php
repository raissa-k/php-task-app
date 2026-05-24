<?php
// Testes unitários do Model Viacao

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Viacao;

/*
 * Por que testar models?
 * No nosso projeto, `fromRow()` converte dados brutos do banco (strings) em tipos PHP corretos.
 * Esse mapeamento é cheio de detalhes: '1' -> true, null -> null, '42' -> 42.
 * Um bug aqui afeta toda a aplicação, mas é difícil de perceber sem testes.
 * Models são o caso mais fácil de testar: sem banco, sem sessão, sem HTTP.
 * Só criamos um array simulando uma linha de banco e verificamos o resultado.
*/

final class ViacaoModelTest extends TestCase
{
    // Helper: retorna uma linha de banco completa e válida pra reutilizar nos testes
    private function defaultRow(): array
    {
        return [
            'id'         => '42',
            'nome'       => 'Expresso Guanabara',
            'cidade'     => 'Rio de Janeiro',
            'ativa'      => '1',
            'logo'       => 'abc123.jpg',
            'created_at' => '2025-01-01 10:00:00',
            'updated_at' => '2025-06-01 12:00:00',
        ];
    }

    // fromRow()

    public function testFromRowMapeiaIdComoInt(): void
    {
        // O banco retorna strings mesmo para colunas INT, fromRow() converte
        $viacao = Viacao::fromRow($this->defaultRow());
        $this->assertSame(42, $viacao->id);
    }

    public function testFromRowMapeiaStringsCamposTexto(): void
    {
        $viacao = Viacao::fromRow($this->defaultRow());
        $this->assertSame('Expresso Guanabara', $viacao->nome);
        $this->assertSame('Rio de Janeiro', $viacao->cidade);
    }

    public function testAtivaCom1EhTrue(): void
    {
        $viacao = Viacao::fromRow($this->defaultRow());
        $this->assertTrue($viacao->ativa);
    }

    public function testAtivaCom0EhFalse(): void
    {
        $row          = $this->defaultRow();
        $row['ativa'] = '0';
        $viacao       = Viacao::fromRow($row);
        $this->assertFalse($viacao->ativa);
    }

    public function testLogoStringEhPreservada(): void
    {
        $viacao = Viacao::fromRow($this->defaultRow());
        $this->assertSame('abc123.jpg', $viacao->logo);
    }

    public function testLogoNullPermaneceNull(): void
    {
        $row        = $this->defaultRow();
        $row['logo'] = null;
        $viacao      = Viacao::fromRow($row);
        $this->assertNull($viacao->logo);
    }

    public function testUpdatedAtStringEhPreservada(): void
    {
        $viacao = Viacao::fromRow($this->defaultRow());
        $this->assertSame('2025-06-01 12:00:00', $viacao->updatedAt);
    }

    public function testUpdatedAtNullPermaneceNull(): void
    {
        $row               = $this->defaultRow();
        $row['updated_at'] = null;
        $viacao            = Viacao::fromRow($row);
        $this->assertNull($viacao->updatedAt);
    }

    // toArray()

    /*
     * toArray() é o caminho inverso: objeto -> array simples.
     * Usamos em respostas JSON e também podemos usar pra passar dados pra views.
     * Testamos que todos os campos aparecem com os valores corretos.
    */

    public function testToArrayContemTodasAsChavesEsperadas(): void
    {
        $arr = Viacao::fromRow($this->defaultRow())->toArray();

        foreach (['id', 'nome', 'cidade', 'ativa', 'logo', 'created_at', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Chave '$key' ausente em toArray()");
        }
    }

    public function testToArrayPreservaValores(): void
    {
        $arr = Viacao::fromRow($this->defaultRow())->toArray();

        $this->assertSame(42, $arr['id']);
        $this->assertSame('Expresso Guanabara', $arr['nome']);
        $this->assertTrue($arr['ativa']);
        $this->assertSame('abc123.jpg', $arr['logo']);
    }

    public function testToArrayComLogoNullRetornaNull(): void
    {
        $row         = $this->defaultRow();
        $row['logo'] = null;
        $arr         = Viacao::fromRow($row)->toArray();
        $this->assertNull($arr['logo']);
    }
}
