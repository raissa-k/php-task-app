<?php
// Model de histórico: ajuda a ler o JSON de before/after

declare(strict_types=1);

namespace App\Models;

final class Historico
{
    public function __construct(
        public int $id,
        public ?int $viacaoId,
        public ?string $viacaoNome,
        public ?int $usuarioId,
        public ?string $usuarioNome,
        public string $acao,
        public array|null $alteracoes,
        public string $criadoEm,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $alter = null;
        if (!empty($row['alteracoes'])) {
            $decoded = json_decode($row['alteracoes'], true);
            if (is_array($decoded)) {
                $alter = $decoded;
            }
        }

        return new self(
            id: (int) $row['id'],
            viacaoId:   $row['viacao_id']   !== null ? (int) $row['viacao_id']  : null,
            viacaoNome: $row['viacao_nome']  ?? null,
            usuarioId:  $row['usuario_id']   !== null ? (int) $row['usuario_id'] : null,
            usuarioNome: $row['usuario_nome'] ?? null,
            acao:        (string) $row['acao'],
            alteracoes:  $alter,
            criadoEm:   (string) $row['criado_em'],
        );
    }

    /**
     * Nome da viação para exibição.
     * "---" se ela foi excluída (JOIN não encontrou).
     * A lógica de fallback fica aqui, não na view: a view só exibe o que o model entrega.
     */
    public function viacaoLabel(): string
    {
        return $this->viacaoNome ?? '---';
    }

    /**
     * Nome do usuário para exibição.
     * "---" se ele foi removido ou a ação não teve usuário.
     */
    public function usuarioLabel(): string
    {
        return $this->usuarioNome ?? '---';
    }

    public function getBefore(): ?array
    {
        return is_array($this->alteracoes['before'] ?? null) ? $this->alteracoes['before'] : null;
    }

    public function getAfter(): ?array
    {
        return is_array($this->alteracoes['after'] ?? null) ? $this->alteracoes['after'] : null;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'viacao_id'    => $this->viacaoId,
            'viacao_nome'  => $this->viacaoNome,
            'usuario_id'   => $this->usuarioId,
            'usuario_nome' => $this->usuarioNome,
            'acao' => $this->acao,
            'alteracoes' => $this->alteracoes,
            'criado_em' => $this->criadoEm,
        ];
    }
}
