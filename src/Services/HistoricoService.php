<?php
// Service do histórico: centraliza as regras de consulta e registro de alterações

declare(strict_types=1);

namespace App\Services;

use App\Models\Historico;
use App\Repositories\HistoricoRepository;

/*
* Por que existe esse service se o repository já faz queries?
* O service fica entre o controller e o repository. Mesmo aqui, onde não há regra de negócio complexa, ele:
* - isola o controller de detalhes de filtro
* - facilita adicionar lógica futura (ex: cache, exportação CSV)
* - mantém o padrão uniforme em todo o projeto
*/

final class HistoricoService
{
   private HistoricoRepository $repo;

   public function __construct(?HistoricoRepository $repo = null)
   {
       $this->repo = $repo ?? new HistoricoRepository();
   }

   /**
    * Retorna registros com filtros opcionais.
    * O controller passa os filtros vindos do $_GET sem processar.
    * O service/repository sanitizam via prepared statements.
    *
    * @return list<Historico>
    */
   public function getHistory(array $filters = []): array
   {
       return $this->repo->findAll($filters);
   }
}
