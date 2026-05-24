<?php
// Testes unitários dos helpers CSRF de View

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\View;

/*
 * Testar código que depende de estado global ($SESSION, $_GET, $_POST) é um desafio em testes PHP.
 * Aqui, View::csrfToken() usa $_SESSION, que só existe após session_start().
 * Em PHPUnit CLI, sessions funcionam normalmente (sem HTTP, usando arquivo temporário), então podemos iniciar a sessão uma vez e limpar $_SESSION entre os testes.
 * O que não dá pra testar facilmente aqui:
 * - View::redirect() - chama header(), que não funciona sem HTTP.
 * - View::render()   - lê arquivos de template, precisaria de caminhos reais.
 * Pesquise "testing legacy PHP", "superglobal injection", "hexagonal architecture".
*/

final class ViewCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Inicia a sessão uma vez por processo (session_start() é idempotente aqui)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Reseta o estado da sessão antes de cada teste, garante isolamento
        $_SESSION = [];
    }

    // csrfToken()

    public function testCsrfTokenGeraStringHex(): void
    {
        $token = View::csrfToken();

        // bin2hex(random_bytes(32)) -> 32 bytes x 2 hex chars = 64 caracteres
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCsrfTokenEhConsistenteNaMesmaSessao(): void
    {
        // Chamar duas vezes deve retornar o mesmo token (reutiliza o da sessão)
        $token1 = View::csrfToken();
        $token2 = View::csrfToken();

        $this->assertSame($token1, $token2);
    }

    public function testCsrfTokenESalvoNaSessao(): void
    {
        $token = View::csrfToken();

        // O token deve estar disponível em $_SESSION para o CsrfMiddleware verificar
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testCsrfTokenReutilizaTokenExistente(): void
    {
        // Se já existe um token na sessão, csrfToken() deve retorná-lo (não gerar novo)
        $_SESSION['csrf_token'] = 'aaaa1111' . str_repeat('0', 56);
        $token                  = View::csrfToken();

        $this->assertSame($_SESSION['csrf_token'], $token);
    }

    // csrfField()

    public function testCsrfFieldRetornaInputHidden(): void
    {
        $field = View::csrfField();

        // Verifica que é um input hidden com name="_csrf"
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf"', $field);
    }

    public function testCsrfFieldContemTokenGerado(): void
    {
        $token = View::csrfToken();
        $field = View::csrfField();

        // O valor do input deve ser o mesmo token da sessão
        $this->assertStringContainsString('value="' . $token . '"', $field);
    }

    public function testCsrfFieldEscapeTokenNoAtributo(): void
    {
        // Mesmo que um token tenha caracteres especiais (improvável com hex, mas defensivo), ele deve ser escapado corretamente para não quebrar o atributo HTML.
        // htmlspecialchars() protege contra XSS em atributos.
        // Pesquise "XSS stored vs reflected", "HTML attribute injection".
        $_SESSION['csrf_token'] = 'abc"def'; // token artificial com aspas
        $field                  = View::csrfField();

        $this->assertStringNotContainsString('value="abc"def"', $field);
        $this->assertStringContainsString('abc&quot;def', $field);
    }

    // flash() e pullFlash()

    public function testFlashSalvaMensagemNaSessao(): void
    {
        View::flash('success', 'Viação criada!');

        $this->assertSame('success', $_SESSION['flash']['type']);
        $this->assertSame('Viação criada!', $_SESSION['flash']['message']);
    }

    public function testPullFlashRetornaERemoveMensagem(): void
    {
        View::flash('danger', 'Erro ao salvar.');
        $flash = View::pullFlash();

        $this->assertSame(['type' => 'danger', 'message' => 'Erro ao salvar.'], $flash);
        // Depois de "puxar", a flash deve ter sumido
        $this->assertArrayNotHasKey('flash', $_SESSION);
    }

    public function testPullFlashSemMensagemRetornaNull(): void
    {
        $flash = View::pullFlash();
        $this->assertNull($flash);
    }

    public function testPullFlashNaoQuebraComDadosMalformados(): void
    {
        // Sessão corrompida não deve lançar exception
        $_SESSION['flash'] = 'string-inesperada';
        $flash             = View::pullFlash();
        $this->assertNull($flash);
    }
}
