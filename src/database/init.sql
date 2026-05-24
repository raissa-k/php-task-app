-- Inicialização do banco de dados
-- Roda automaticamente quando o container MySQL sobe pela primeira vez.
-- Pesquise "Docker entrypoint initdb.d" pra entender quando isso é executado.

-- utf8mb4: suporte completo a Unicode, incluindo emojis (4 bytes por caractere).
-- O "utf8" do MySQL é limitado, "utf8mb4" é o utf8 de verdade.
-- utf8mb4_unicode_ci: collation que compara letras sem diferenciar maiúscula de minúscula (ci = case insensitive) e com suporte a acentos correto pra sort e busca.
-- Pesquise "MySQL utf8mb4", "collation vs charset".
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usuários

-- IF NOT EXISTS: o script pode ser executado mais de uma vez sem dar erro.
-- Isso é importante porque o Docker pode rodar init.sql em reinicializações.
-- Pesquise "idempotent SQL migrations".
CREATE TABLE IF NOT EXISTS usuarios (
  -- INT UNSIGNED: inteiros sem sinal (sem negativo). Dobra o range positivo: 0 a ~4 bilhões.
  -- AUTO_INCREMENT: o banco gera o ID automaticamente em cada INSERT.
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(255)  NOT NULL,
  -- UNIQUE garante que não haverá dois usuários com o mesmo email. O banco cria um índice automático pra essa coluna (essencial pra performance de login).
  email      VARCHAR(255)  NOT NULL UNIQUE,
  -- Armazena o hash bcrypt NUNCA a senha em texto puro.
  senha      VARCHAR(255)  NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuários são inseridos pelo script src/cli/seed.php, não aqui.
-- Motivo: hashes de senha precisam ser gerados pelo PHP em runtime (password_hash).
-- NUNCA guarde senhas em texto puro em SQL.

-- Viações

CREATE TABLE IF NOT EXISTS viacoes (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(255)  NOT NULL,
  cidade     VARCHAR(255)  NOT NULL,
  -- TINYINT(1) é a convenção pra booleanos: 0 = falso, 1 = verdadeiro.
  -- O PHP lê como int, por isso fazemos a conversão explícita no Model (Viacao::fromRow).
  ativa      TINYINT(1)    NOT NULL DEFAULT 1,
  -- NULL permitido: logo é opcional.
  logo       VARCHAR(255)  NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- ON UPDATE CURRENT_TIMESTAMP: atualiza automaticamente toda vez que o registro muda.
  -- Não precisamos setar updated_at no PHP, o banco cuida disso.
  -- NULL DEFAULT NULL: começa nulo e só recebe valor após o primeiro UPDATE.
  updated_at TIMESTAMP     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Viações e histórico de demo são inseridos pelo script src/cli/seed.php.
-- init.sql só define o schema, dados ficam no seed pra ter lógica PHP disponível

-- Histórico de alterações

CREATE TABLE IF NOT EXISTS viacoes_historico (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  -- NULL permitido: por questões de demonstração, viação pode ter sido excluída e o ID deixou de existir dependendo da ordem de processamento no sistema.
  viacao_id  INT           NULL,
  -- NULL permitido: algumas ações podem ser feitas sem usuário logado (ex: seed).
  usuario_id INT           NULL,
  acao       VARCHAR(64)   NOT NULL,
  -- JSON: tipo nativo do MySQL 5.7+. O banco valida que o conteúdo é JSON válido e permite consultas dentro da estrutura (ex: JSON_EXTRACT).
  -- Aqui guardamos {"before": {...}, "after": {...}} pra rastrear o que mudou.
  -- Pesquise "MySQL JSON type", "JSON_EXTRACT MySQL".
  alteracoes JSON          NOT NULL,
  criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Índices nas chaves estrangeiras: sem eles, filtrar por viacao_id ou usuario_id faria uma varredura completa da tabela a cada query.
  -- Regra geral: toda coluna usada em WHERE ou JOIN deve ter um índice.
  -- Pesquise "database index", "query execution plan".
  INDEX idx_viacao  (viacao_id),
  INDEX idx_usuario (usuario_id)

  -- Por que não FOREIGN KEY aqui?
  -- Chaves estrangeiras com ON DELETE RESTRICT impediriam excluir uma viação que tem histórico, ou exigiriam ON DELETE CASCADE (apagar o histórico junto).
  -- A decisão foi manter o histórico mesmo após a exclusão, por isso viacao_id é INT NULL sem FOREIGN KEY.
  -- Logs de auditoria devem sobreviver ao objeto auditado.

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ENGINE=InnoDB: mecanismo de armazenamento padrão do MySQL moderno.
-- Suporta transações (BEGIN/COMMIT/ROLLBACK), chaves estrangeiras e crash recovery.
-- Pesquise "ACID properties", "database transactions".
