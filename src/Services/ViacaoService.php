<?php
// Service de viações: regra de negócio de criação, edição, exclusão e registro de histórico

declare(strict_types=1);

namespace App\Services;

use App\Models\Viacao;
use App\Repositories\HistoricoRepository;
use PDO;

/*
 * O que esse service faz além do CRUD básico:
 * Toda operação que modifica uma viação (criar, editar, excluir) também registra um log de histórico com o "antes" e o "depois".
 * Isso permite rastrear quem fez o quê e quando.
 * Para garantir que o log sempre seja salvo junto com a alteração, usamos transações de banco de dados. Se qualquer etapa falhar, tudo é desfeito (rollback).
 * Pesquise "ACID properties" e "database transactions" pra entender o conceito.
*/

final class ViacaoService
{
    private PDO $pdo;
    private HistoricoRepository $historicoRepo;

    public function __construct(
        ?PDO                  $pdo          = null,
        ?HistoricoRepository  $historicoRepo = null,
    ) {
        $this->pdo          = $pdo           ?? \getPdo();
        $this->historicoRepo = $historicoRepo ?? new HistoricoRepository($this->pdo);
    }

    /**
     * Retorna viações do painel admin, com filtros opcionais de busca e status.
     *
     * Por que usar prepare() mesmo sem filtros?
     * Fica consistente (sempre usa a mesma path de execução) e evita dois branches de código (query() para sem filtros, prepare() para com filtros).
     * O overhead de prepare() sem parâmetros é insignificante.
     *
     * @return list<Viacao>
     */
    public function all(string $q = '', ?bool $ativa = null): array
    {
        $wheres = [];
        $params = [];

        if ($q !== '') {
            /*
             * PDO não permite reutilizar o mesmo placeholder nomeado (:q) duas vezes na mesma query, cada ocorrência precisa de um nome único.
             * Por isso usamos :q pra nome e :q2 pra cidade com o mesmo valor.
            */
            $wheres[] = '(nome LIKE :q OR cidade LIKE :q2)';
            $params['q']  = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }

        if ($ativa !== null) {
            $wheres[] = 'ativa = :ativa';
            $params['ativa'] = $ativa ? 1 : 0;
        }

        $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $stmt = $this->pdo->prepare("SELECT * FROM viacoes {$where} ORDER BY id DESC");
        $stmt->execute($params);
        return array_map([Viacao::class, 'fromRow'], $stmt->fetchAll());
    }

    /**
     * Retorna só as viações ativas.
     * Usada na home pública, visitante não precisa ver viações inativas.
     *
     * @return list<Viacao>
     */
    public function active(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM viacoes WHERE ativa = 1 ORDER BY id DESC');
        $stmt->execute();
        return array_map([Viacao::class, 'fromRow'], $stmt->fetchAll());
    }

    /** Busca uma viação pelo ID. Retorna null se não encontrar. */
    public function find(int $id): ?Viacao
    {
        $stmt = $this->pdo->prepare('SELECT * FROM viacoes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? Viacao::fromRow($row) : null;
    }

    /**
     * Cria uma nova viação e registra no histórico.
     * Retorna o ID gerado pelo banco.
     *
     * @throws \Throwable em caso de erro de banco (a transação garante rollback)
     */
    public function create(string $nome, string $cidade, bool $ativa, ?string $logo, ?int $usuarioId = null): int
    {
        /*
         * Por que beginTransaction() aqui?
         * Fazemos duas operações: INSERT na tabela viacoes + INSERT no histórico.
         * Se o INSERT do histórico falhar por algum motivo (ex: banco cheio), a viação já teria sido criada sem registro (estado inconsistente).
         * beginTransaction() garante que as duas operações sejam atômicas: ou as duas acontecem, ou nenhuma.
         * Se qualquer exceção for lançada, o catch faz rollback e desfaz tudo.
        */
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO viacoes (nome, cidade, ativa, logo) VALUES (:nome, :cidade, :ativa, :logo)'
            );
            $stmt->execute([
                'nome'   => $nome,
                'cidade' => $cidade,
                'ativa'  => $ativa ? 1 : 0,
                'logo'   => $logo,
            ]);

            $id = (int) $this->pdo->lastInsertId();

            // Histórico: before = null (era vazio), after = estado atual
            $this->historicoRepo->create($id, $usuarioId, 'Criado', null, $this->findRow($id));

            $this->pdo->commit();
            return $id;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e; // relança pra o controller tratar (ex: renderizar erro)
        }
    }

    /** Edita uma viação e registra o antes/depois no histórico. */
    public function update(int $id, string $nome, string $cidade, bool $ativa, ?string $logo, ?int $usuarioId = null): void
    {
        $this->pdo->beginTransaction();

        try {
            // Captura o estado ANTES da edição pro histórico de "antes"
            $before    = $this->findRow($id);
            $oldLogo   = $before['logo'] ?? null;

            $stmt = $this->pdo->prepare(
                'UPDATE viacoes SET nome = :nome, cidade = :cidade, ativa = :ativa, logo = :logo,
                 updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $stmt->execute([
                'id'     => $id,
                'nome'   => $nome,
                'cidade' => $cidade,
                'ativa'  => $ativa ? 1 : 0,
                'logo'   => $logo,
            ]);

            // Captura o estado DEPOIS pro histórico de "depois"
            $after = $this->findRow($id);

            // Só salva os campos que realmente mudaram e não o registro inteiro
            [$diffBefore, $diffAfter] = $this->diffRows($before, $after);
            $this->historicoRepo->create($id, $usuarioId, 'Editado', $diffBefore, $diffAfter);

            $this->pdo->commit();

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        /*
         * Remoção do logo antigo DEPOIS do commit (fora da transação).
         * Por que depois e não dentro?
         * Arquivos não participam de transações de banco.
         * Se deletarmos o arquivo dentro do try e depois o commit falhar, o arquivo sumiu mas o banco fez rollback (estado inconsistente).
         * Fora do try, só chegamos aqui se o commit já foi bem-sucedido.
         * Se deletar falhar (arquivo já sumiu, problema de permissão, etc), o banco está consistente e podemos ignorar o erro com segurança.
        */
        if ($oldLogo !== null && $oldLogo !== $logo) {
            $this->deleteLogoFile($oldLogo);
        }
    }

    /** Exclui uma viação e registra no histórico (after = null, pois sumiu). */
    public function delete(int $id, ?int $usuarioId = null): void
    {
        $this->pdo->beginTransaction();

        try {
            $before  = $this->findRow($id);
            $oldLogo = $before['logo'] ?? null;

            $stmt = $this->pdo->prepare('DELETE FROM viacoes WHERE id = :id');
            $stmt->execute(['id' => $id]);

            // Histórico: after = null porque a viação não existe mais
            $this->historicoRepo->create($id, $usuarioId, 'Excluido', $before, null);

            $this->pdo->commit();

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // Remove o arquivo de logo depois do commit bem-sucedido
        if ($oldLogo !== null) {
            $this->deleteLogoFile($oldLogo);
        }
    }

    /**
     * Compara o estado antes e depois da edição e retorna só os campos que mudaram.
     *
     * Por que comparar antes de salvar?
     * Se o usuário abriu o form de edição e clicou em "Salvar" sem mudar nada, o log teria um "Editado" com before == after, inútil e enganoso.
     * Assim o histórico reflete apenas o que realmente foi alterado.
     *
     * Por que excluir id, created_at e updated_at?
     * Esses campos não representam mudança de negócio: o id nunca muda, created_at é imutável e updated_at muda em todo UPDATE automaticamente.
     * Incluir poluiria o histórico sem agregar informação.
     *
     * @return array{0: ?array, 1: ?array}
     */
    private function diffRows(?array $before, ?array $after): array
    {
        $skip = ['id', 'created_at', 'updated_at'];

        $allKeys = array_unique(array_merge(
            array_keys($before ?? []),
            array_keys($after ?? [])
        ));

        $diffBefore = [];
        $diffAfter  = [];

        foreach ($allKeys as $key) {
            if (in_array($key, $skip, true)) {
                continue;
            }

            $valBefore = $before[$key] ?? null;
            $valAfter  = $after[$key]  ?? null;

            // Comparação estrita: ambos os lados vêm do mesmo SELECT, então os tipos batem
            if ($valBefore !== $valAfter) {
                $diffBefore[$key] = $valBefore;
                $diffAfter[$key]  = $valAfter;
            }
        }

        // Retorna null quando não há diferença (ex.: salvar sem alterar nada)
        return [$diffBefore ?: null, $diffAfter ?: null];
    }

    /**
     * Remove o arquivo de logo do storage, ignorando erros silenciosamente.
     * Por que basename()?
     * Defesa extra contra path traversal: se $filename vier com "../" por algum bug, basename() descarta o caminho e deixa só o nome do arquivo.
     * O UploadService já faz essa proteção ao salvar, mas repetir aqui é barato e seguro.
     *
     * Por que não lançar exceção se o arquivo não existir?
     * O logo pode ter sido apagado manualmente, migrado ou nunca ter chegado ao discopor erro anterior.
     * O banco já está consistente (commit feito) e falhar aqui seria pior do que simplesmente pular.
     */
    private function deleteLogoFile(string $filename): void
    {
        $path = new UploadService()->storagePath(basename($filename));
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Busca a linha crua (array) de uma viação, usado internamente pra capturar o estado before/after sem converter pro model Viacao (que perderia colunas raw).
     */
    private function findRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM viacoes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}
