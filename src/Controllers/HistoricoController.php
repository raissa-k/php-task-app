<?php
// Controller administrativo: lista histórico de alterações com filtros

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\HistoricoService;
use App\Validators\HistoricoFilterValidator;

final class HistoricoController
{
    private HistoricoService $service;

    public function __construct(?HistoricoService $service = null)
    {
        // Usa HistoricoService e não ViacaoService porque histórico é responsabilidade própria
        $this->service = $service ?? new HistoricoService();
    }

    public function index(): void
    {
        /*
         * HistoricoFilterValidator centraliza toda a lógica de sanitização dos filtros:
         * - viacao_id / usuario_id: cast pra int, descarta valores negativos ou zero
         * - acao: allowlist contra ['Criado', 'Editado', 'Excluido']
         * - date_from / date_to: valida formato Y-m-d e que a data é real
         * - q: trim + cast
         * Antes essa lógica estava espalhada aqui no controller.
         * Centralizar no validator significa um único lugar pra atualizar quando as regras mudarem.
         */
        $filters   = new HistoricoFilterValidator()->parse($_GET);
        $historico = $this->service->getHistory($filters);

        View::render('admin/historico/index', [
            'title'     => 'Histórico de alterações',
            'historico' => $historico,
            'filters'   => $filters,
        ]);
    }
}
