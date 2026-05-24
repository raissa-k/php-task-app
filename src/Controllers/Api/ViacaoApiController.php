<?php
// API REST de viações: autenticação por token de header, responde JSON

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ViacaoService;
use App\Validators\ViacaoValidator;

/*
 * Por que uma API separada do controller web?
 * O controller web (ViacaoController) lida com formulários HTML, sessões e redirecionamentos.
 * Este controller lida com JSON, tokens de acesso e respostas sem HTML, misturar as duas coisas torna o código confuso.
 * Um usuário humano usa o painel web, e outro sistema (app mobile, integração) usa essa API.
*/

final class ViacaoApiController
{
    private ViacaoService $service;
    private ViacaoValidator $validator;

    public function __construct(?ViacaoService $service = null, ?ViacaoValidator $validator = null)
    {
        $this->service   = $service   ?? new ViacaoService();
        $this->validator = $validator ?? new ViacaoValidator();
    }

    // Rotas públicas (sem autenticação)

    /** Lista todas as viações. Endpoint público, sem token. */
    public function index(): void
    {
        $viacoes = $this->service->all();
        $data    = array_map(fn($v) => $v->toArray(), $viacoes);

        $this->jsonResponse(['ok' => true, 'count' => count($data), 'data' => $data]);
    }

    /** Retorna uma viação pelo ID. Endpoint público, sem token. */
    public function show(int $id): void
    {
        $v = $this->service->find($id);

        if ($v === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }

        $this->jsonResponse(['ok' => true, 'data' => [
            'id'         => $v->id,
            'nome'       => $v->nome,
            'cidade'     => $v->cidade,
            'ativa'      => $v->ativa,
            'logo'       => $v->logo,
            'created_at' => $v->createdAt,
            'updated_at' => $v->updatedAt,
        ]]);
    }

    // Rotas protegidas (exigem token)

    /** Cria uma nova viação. Exige token. */
    public function store(): void
    {
        if (!$this->authorize()) {
            $this->jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $input  = $this->parseJsonInput();
        $result = $this->validator->validate($input);

        if (!empty($result['errors'])) {
            $this->jsonResponse(['ok' => false, 'errors' => $result['errors']], 422);
            return;
        }

        $data = $result['data'];
        $id   = $this->service->create($data['nome'], $data['cidade'], $data['ativa'], $data['logo'] ?? null);

        $this->jsonResponse(['ok' => true, 'id' => $id], 201);
    }

    /** Atualiza uma viação. Exige token. */
    public function update(int $id): void
    {
        if (!$this->authorize()) {
            $this->jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $v = $this->service->find($id);

        if ($v === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }

        $input  = $this->parseJsonInput();
        $result = $this->validator->validate($input);

        if (!empty($result['errors'])) {
            $this->jsonResponse(['ok' => false, 'errors' => $result['errors']], 422);
            return;
        }

        $data = $result['data'];
        $this->service->update($id, $data['nome'], $data['cidade'], $data['ativa'], $v->logo ?? null);

        $this->jsonResponse(['ok' => true]);
    }

    /** Remove uma viação. Exige token. */
    public function destroy(int $id): void
    {
        if (!$this->authorize()) {
            $this->jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $v = $this->service->find($id);

        if ($v === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }

        $this->service->delete($id);
        $this->jsonResponse(['ok' => true]);
    }

    // Helpers privados

    /**
     * Verifica o token de API enviado no header X-API-TOKEN.
     *
     * Por que header e não query string (?token=xxx)?
     * Headers não aparecem em logs de servidor nem no histórico do browser.
     * Query strings ficam na URL, que é logada em todo lugar então há risco de vazar o token.
     *
     * Por que hash_equals() e não ===?
     * A comparação === tem "timing attack" vulnerável: ela para de comparar no primeiro caractere diferente.
     * Isso permite que um atacante adivinhe o token caractere por caractere medindo o tempo de resposta.
     *
     * hash_equals() sempre compara todos os bytes, tornando o tempo constante independente de onde os tokens diferem.
     * Pesquise "timing attack", "side-channel attack", "constant-time comparison".
     */
    private function authorize(): bool
    {
        $expected = getenv('API_TOKEN') ?: 'changeme';
        $token    = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['REDIRECT_HTTP_X_API_TOKEN'] ?? null;

        return is_string($token) && hash_equals($expected, $token);
    }

    /**
     * Lê o corpo do request como JSON, cai no $_POST se não for JSON válido.
     *
     * APIs REST enviam dados no corpo do request em formato JSON.
     * O PHP não popula $_POST automaticamente pra Content-Type: application/json, apenas pra application/x-www-form-urlencoded (formulários HTML padrão).
     *
     * O fallback pra $_POST é conveniente pra testes com formulários HTML (ex: Postman no modo "form data"), mas em produção o cliente deve enviar JSON.
     *
     * Pesquise "php://input", "Content-Type header", "REST API conventions".
     */
    private function parseJsonInput(): array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return $_POST;
        }

        return $data;
    }

    /**
     * Envia uma resposta JSON com o status HTTP correto.
     *
     * JSON_UNESCAPED_UNICODE mantém caracteres em vez de converter "ã" (U+00E3) -> "ã" ("\u00e3"), etc.
     * Isso facilita debugar a API e reduz o tamanho da resposta.
     */
    private function jsonResponse(mixed $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
