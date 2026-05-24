<?php
// Controller de viações: recebe request, valida e chama o service

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Http\Request;
use App\Http\ValidationException;
use App\Services\AuthService;
use App\Services\UploadService;
use App\Services\ViacaoService;
use App\Validators\ViacaoFilterValidator;
use App\Validators\ViacaoValidator;

/*
 * Padrão dos controllers neste projeto:
 * 1. Receber os dados do request (GET params, $_POST, $_FILES)
 * 2. Validar via Request + Validator (lança exception se POST inválido)
 * 3. Delegar a lógica de negócio pro Service
 * 4. Redirecionar ou renderizar a view
 * O controller NÃO acessa o banco diretamente, isso é responsabilidade do Service ou do Repository.
 * O controller NÃO valida regras de negócio, isso é responsabilidade do Service.
 * O controller NÃO formata dados pra HTML, isso é responsabilidade da View.
 * Pesquise "MVC responsibilities".
*/

final class ViacaoController
{
    private ViacaoService $service;
    private AuthService $auth;

    /*
     * AuthService é injetado via construtor igual ao ViacaoService.
     * Por que não criar com "new AuthService()" dentro dos métodos?
     * 1. Consistência: todos os controllers seguem o mesmo padrão
     * 2. Testabilidade: num teste, você pode passar um AuthService falso (mock)
     * 3. Clareza: ao ler o construtor, você sabe exatamente de quem esse controller depende e não precisa ler o corpo de cada método
     *
     * O parâmetro é nullable (?AuthService) com padrão null pra que o Router possa instanciar o controller sem parâmetros (new ViacaoController()).
     * Se nenhum valor for passado, criamos a dependência aqui.
     * Esse padrão é chamado de "poor man's dependency injection". xD
    */
    public function __construct(
        ?ViacaoService $service = null,
        ?AuthService   $auth    = null,
    ) {
        $this->service = $service ?? new ViacaoService();
        $this->auth    = $auth    ?? new AuthService();
    }

    /** Lista viações no painel admin, com filtros opcionais de busca e status. */
    public function index(): void
    {
        $filters = new ViacaoFilterValidator()->parse($_GET);
        $viacoes = $this->service->all($filters['q'], $filters['ativa']);

        View::render('admin/viacoes/index', [
            'title'   => 'Viações',
            'viacoes' => $viacoes,
            'filters' => $filters,
        ]);
    }

    /** Exibe o formulário de cadastro. */
    public function create(): void
    {
        View::render('admin/viacoes/create', [
            'title'  => 'Cadastrar Viação',
            'errors' => [],
            'old'    => ['nome' => '', 'cidade' => '', 'ativa' => true],
        ]);
    }

    /** Processa o form de cadastro: valida, faz upload e salva. */
    public function store(): void
    {
        $request = new Request($_POST, $_FILES, new ViacaoValidator());

        try {
            $data = $request->validated();
        } catch (ValidationException $ve) {
            View::render('admin/viacoes/create', [
                'title'  => 'Cadastrar Viação',
                'errors' => $ve->getErrors(),
                'old'    => $_POST,
            ]);
            return;
        }

        try {
            $logo = $this->tryUploadLogo();
        } catch (\Throwable $e) {
            View::render('admin/viacoes/create', [
                'title'  => 'Cadastrar Viação',
                'errors' => [$e->getMessage()],
                'old'    => $_POST,
            ]);
            return;
        }

        $id = $this->service->create($data['nome'], $data['cidade'], $data['ativa'], $logo, $this->auth->userId());

        View::flash('success', 'Viação criada com sucesso (#' . $id . ').');
        View::redirect('/admin/viacoes');
    }

    /** Exibe o formulário de edição com os dados atuais. */
    public function edit(int $id): void
    {
        $viacao = $this->service->find($id);

        if ($viacao === null) {
            $this->abort404('Viação não encontrada.');
            return;
        }

        View::render('admin/viacoes/edit', [
            'title'  => 'Editar Viação',
            'viacao' => $viacao,
            'errors' => [],
            'old'    => [
                'nome'   => $viacao->nome,
                'cidade' => $viacao->cidade,
                'ativa'  => $viacao->ativa,
            ],
        ]);
    }

    /** Processa o form de edição. */
    public function update(int $id): void
    {
        $viacao = $this->service->find($id);

        if ($viacao === null) {
            $this->abort404('Viação não encontrada.');
            return;
        }

        $request = new Request($_POST, $_FILES, new ViacaoValidator());

        try {
            $data = $request->validated();
        } catch (ValidationException $ve) {
            View::render('admin/viacoes/edit', [
                'title'  => 'Editar Viação',
                'viacao' => $viacao,
                'errors' => $ve->getErrors(),
                'old'    => $_POST,
            ]);
            return;
        }

        // Se não enviou novo logo, mantém o que já tinha
        $logo = $viacao->logo;

        if (!empty($_FILES['logo']['name'])) {
            try {
                $logo = $this->tryUploadLogo();
            } catch (\Throwable $e) {
                View::render('admin/viacoes/edit', [
                    'title'  => 'Editar Viação',
                    'viacao' => $viacao,
                    'errors' => [$e->getMessage()],
                    'old'    => $_POST,
                ]);
                return;
            }
        }

        $this->service->update($id, $data['nome'], $data['cidade'], $data['ativa'], $logo, $this->auth->userId());

        View::flash('success', 'Viação atualizada.');
        View::redirect('/admin/viacoes');
    }

    /** Remove a viação do banco. */
    public function destroy(int $id): void
    {
        $viacao = $this->service->find($id);

        if ($viacao === null) {
            $this->abort404('Viação não encontrada.');
            return;
        }

        $this->service->delete($id, $this->auth->userId());

        View::flash('success', 'Viação removida.');
        View::redirect('/admin/viacoes');
    }

    /**
     * Tenta fazer upload do logo enviado no $_FILES['logo'].
     * Retorna o nome do arquivo salvo, ou null se nenhum arquivo foi enviado.
     * Lança RuntimeException em caso de tipo ou tamanho inválido.
     */
    private function tryUploadLogo(): ?string
    {
        if (empty($_FILES['logo']['name'])) {
            return null;
        }

        return new UploadService()->handleUpload($_FILES['logo']);
    }

    /**
     * Encerra o request com status 404 e uma mensagem de erro.
     * Centralizado aqui pra não repetir o par http_response_code + echo em todo método.
     */
    private function abort404(string $message): void
    {
        http_response_code(404);
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
}
