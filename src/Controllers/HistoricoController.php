<?php
// Controller administrativo: lista histórico de alterações com filtros

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\HistoricoService;

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
        // Lê filtros do GET com valores padrão seguros
        $filters = [
            'viacao_id'  => isset($_GET['viacao_id'])  ? (int) $_GET['viacao_id']  : null,
            'usuario_id' => isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : null,
            'acao'       => $_GET['acao']      ?? null,
            'date_from'  => $_GET['date_from'] ?? null,
            'date_to'    => $_GET['date_to']   ?? null,
            'q'          => $_GET['q']         ?? null,
        ];

        $historico = $this->service->getHistory($filters);

        View::render('admin/historico/index', [
            'title'     => 'Histórico de alterações',
            'historico' => $historico,
            'filters'   => $filters,
        ]);
    }
}
