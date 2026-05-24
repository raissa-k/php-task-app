<?php
// Controller da home pública: carrega só viações ativas

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\ViacaoService;

final class HomeController
{
    private ViacaoService $service;

    public function __construct(?ViacaoService $service = null)
    {
        $this->service = $service ?? new ViacaoService();
    }

    public function index(): void
    {
        $viacoes = $this->service->active();

        View::render('home/index', [
            'title' => 'Quero Passagem',
            'viacoes' => $viacoes,
            '_layout' => '_layout_public',
        ]);
    }
}
