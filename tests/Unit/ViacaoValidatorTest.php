<?php
// Testes unitários do ViacaoValidator

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Validators\ViacaoValidator;

/*
 * O que é um teste unitário?
 * Testa uma única "unidade" (classe ou função) de forma isolada.
 * Sem banco, sem HTTP, sem sessão.
 * O objetivo é verificar que a lógica da classe está correta, independentedo resto do sistema.
 * Se um teste unitário falha, você sabe exatamente onde está o problema.
 * Pesquise "unit test vs integration test vs end-to-end test", "test pyramid".
*/

final class ViacaoValidatorTest extends TestCase
{
    private ViacaoValidator $validator;

    /*
     * setUp() é chamado pelo PHPUnit antes de cada método de teste.
     * Recria o validator do zero, garantindo que um teste não afeta o próximo.
     * Isso é "test isolation", cada teste é independente.
     * Pesquise "test fixtures", "setUp e tearDown PHPUnit".
    */
    protected function setUp(): void
    {
        $this->validator = new ViacaoValidator();
    }

    // Casos válidos

    public function testDadosCompletosNaoProduzemErros(): void
    {
        $result = $this->validator->validate([
            'nome'   => 'Expresso Guanabara',
            'cidade' => 'Rio de Janeiro',
            'ativa'  => '1',
        ]);

        $this->assertEmpty($result['errors']);
    }

    public function testDadosValidosRetornamCamposSanitizados(): void
    {
        $result = $this->validator->validate([
            'nome'   => 'Expresso Guanabara',
            'cidade' => 'Rio de Janeiro',
            'ativa'  => '1',
        ]);

        // assertSame: verifica valor E tipo.
        // Pesquise "assertSame vs assertEquals PHPUnit".
        $this->assertSame('Expresso Guanabara', $result['data']['nome']);
        $this->assertSame('Rio de Janeiro', $result['data']['cidade']);
        $this->assertTrue($result['data']['ativa']);
    }

    // Validação de nome

    public function testNomeVazioProduzeErro(): void
    {
        $result = $this->validator->validate(['nome' => '', 'cidade' => 'SP']);
        $this->assertContains('O nome é obrigatório.', $result['errors']);
    }

    public function testNomeSoComEspacosEhInvalido(): void
    {
        // trim() deve rejeitar strings que só têm whitespace
        $result = $this->validator->validate(['nome' => '   ', 'cidade' => 'SP']);
        $this->assertContains('O nome é obrigatório.', $result['errors']);
    }

    public function testNomeComEspacosNasExtremidadesEhTrimado(): void
    {
        // O dado retornado em 'data' deve ter os espaços removidos
        $result = $this->validator->validate(['nome' => '  Cometa  ', 'cidade' => 'Curitiba']);
        $this->assertSame('Cometa', $result['data']['nome']);
    }

    /*
     * Boundary testing: testa os limites exatos de uma regra.
     * 255 caracteres deve ser válido; 256 deve falhar.
    */
    public function testNomeComExatamente255CaracteresEhValido(): void
    {
        $result = $this->validator->validate([
            'nome'   => str_repeat('a', 255),
            'cidade' => 'Curitiba',
        ]);
        $this->assertEmpty($result['errors']);
    }

    public function testNomeCom256CaracteresProduzeErro(): void
    {
        $result = $this->validator->validate([
            'nome'   => str_repeat('a', 256),
            'cidade' => 'Curitiba',
        ]);
        $this->assertContains('O nome deve ter no máximo 255 caracteres.', $result['errors']);
    }

    // Validação de cidade

    public function testCidadeVaziaProduzeErro(): void
    {
        $result = $this->validator->validate(['nome' => 'Cometa', 'cidade' => '']);
        $this->assertContains('A cidade é obrigatória.', $result['errors']);
    }

    public function testCidadeCom256CaracteresProduzeErro(): void
    {
        $result = $this->validator->validate([
            'nome'   => 'Cometa',
            'cidade' => str_repeat('b', 256),
        ]);
        $this->assertContains('A cidade deve ter no máximo 255 caracteres.', $result['errors']);
    }

    // Múltiplos erros

    public function testAmbosCamposVaziosProduzemDoisErros(): void
    {
        $result = $this->validator->validate(['nome' => '', 'cidade' => '']);
        // count() de erros: garante que TODOS os erros são reportados de uma vez, não apenas o primeiro. Isso melhora a UX do formulário.
        $this->assertCount(2, $result['errors']);
    }

    // Campo ativa

    /*
     * O campo 'ativa' tem coerção de tipo: aceita string '1' (de formulário HTML) e bool true (de JSON da API).
    */

    public function testAtivaComStringUmEhTrue(): void
    {
        $result = $this->validator->validate(['nome' => 'X', 'cidade' => 'Y', 'ativa' => '1']);
        $this->assertTrue($result['data']['ativa']);
    }

    public function testAtivaComBoolTrueEhTrue(): void
    {
        $result = $this->validator->validate(['nome' => 'X', 'cidade' => 'Y', 'ativa' => true]);
        $this->assertTrue($result['data']['ativa']);
    }

    public function testAtivaAusenteEhFalse(): void
    {
        $result = $this->validator->validate(['nome' => 'X', 'cidade' => 'Y']);
        $this->assertFalse($result['data']['ativa']);
    }

    public function testAtivaComStringZeroEhFalse(): void
    {
        $result = $this->validator->validate(['nome' => 'X', 'cidade' => 'Y', 'ativa' => '0']);
        $this->assertFalse($result['data']['ativa']);
    }

    public function testAtivaComBoolFalseEhFalse(): void
    {
        $result = $this->validator->validate(['nome' => 'X', 'cidade' => 'Y', 'ativa' => false]);
        $this->assertFalse($result['data']['ativa']);
    }

    // Estrutura do retorno

    public function testRetornoSempreTemChavesErrorsEData(): void
    {
        $result = $this->validator->validate([]);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testLogoRetornadoEhSempreNull(): void
    {
        // O validator não trata upload, logo sempre volta null
        $result = $this->validator->validate(['nome' => 'X', 'cidade' => 'Y', 'logo' => 'qualquer']);
        $this->assertNull($result['data']['logo']);
    }
}
