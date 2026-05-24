# Viações Demo - PHP MVC sem framework

Projeto de referência para estagiários praticarem uma arquitetura MVC organizada antes de entrar em Laravel. 
Todo o código tem comentários explicando **por quê** cada decisão foi tomada, não apenas o quê.

---

## Sumário

1. [Como rodar](#como-rodar)
2. [Objetivo didático](#objetivo-didático)
3. [Arquitetura: fluxo de uma requisição](#arquitetura-fluxo-de-uma-requisição)
4. [Estrutura do projeto](#estrutura-do-projeto)
5. [Painel admin](#painel-admin)
6. [Rotas web](#rotas-web)
7. [API REST](#api-rest)
8. [CLI scripts](#cli-scripts)
9. [Testes](#testes)
10. [Docker: quando reconstruir a imagem](#docker-quando-reconstruir-a-imagem)
11. [Banco de dados](#banco-de-dados)
12. [PSR-4 e autoload](#psr-4-e-autoload)
13. [Próximos passos sugeridos](#próximos-passos-sugeridos)

---

## Como rodar

```bash
# 1. Copie as variáveis de ambiente
cp .env.example .env

# 2. Suba os containers (init.sql cria as tabelas automaticamente)
docker compose up --build -d

# 3. Popule o banco: cria usuário admin, viações e logs de demo
docker compose exec viacoes_php_demo_app php src/cli/seed.php
```

Acesse: **http://localhost:8081**

Login: `admin@admin.com` / `admin123`

```bash
# Parar sem apagar dados:
docker compose down

# Parar E apagar o banco (começar do zero):
docker compose down -v
docker compose up --build -d
docker compose exec viacoes_php_demo_app php src/cli/seed.php
```

---

## Objetivo didático

Este projeto demonstra uma app web PHP completa construída à mão, sem framework.
O objetivo é entender o que o Laravel (e outros frameworks) fazem por você.

**O que está implementado:**

| Conceito                                                   | Onde ver                                                                |
|------------------------------------------------------------|-------------------------------------------------------------------------|
| Front controller + roteamento com parâmetros               | `src/public/index.php`, `src/Core/Router.php`                           |
| Method spoofing (PUT/DELETE de forms HTML)                 | `src/public/index.php`, `src/routes/web.php`                            |
| Camadas MVC (Controller -> Service -> Repository -> Model) | `src/Controllers/`, `src/Services/`, `src/Repositories/`, `src/Models/` |
| Transações de banco (atomicidade ACID)                     | `src/Services/ViacaoService.php`                                        |
| CRUD completo com MySQL e PDO prepared statements          | `src/Services/ViacaoService.php`, `src/Repositories/`                   |
| Proteção CSRF via token de sessão                          | `src/Middleware/CsrfMiddleware.php`, `src/Core/View.php`                |
| Autenticação por sessão (httponly, SameSite=Lax)           | `src/Services/AuthService.php`                                          |
| Middleware de autenticação (protege rotas admin)           | `src/Middleware/AuthMiddleware.php`                                     |
| Histórico diff-only (before/after só do que mudou)         | `src/Services/ViacaoService.php` -> `diffRows()`                        |
| Upload de arquivo (validação MIME, fora do docroot)        | `src/Services/UploadService.php`                                        |
| Flash messages e padrão PRG (Post/Redirect/Get)            | `src/Core/View.php`                                                     |
| API REST com token de header                               | `src/Controllers/Api/ViacaoApiController.php`                           |
| CLI scripts (seed, import em lote)                         | `src/cli/`                                                              |
| Suite de testes (PHPUnit, SQLite em memória)               | `tests/`                                                                |

---

## Arquitetura: fluxo de uma requisição

Toda requisição web passa por este caminho:

```
Browser
  │
  ▼
src/public/index.php        <- Front controller: ponto de entrada único
  │  1. Inicia sessão (se não for /api)
  │  2. Registra middlewares (CSRF, Auth)
  │  3. Method spoofing: POST + _method=DELETE -> trata como DELETE
  │  4. Router::dispatch($method, $uri)
  │
  ▼
src/Core/Router.php         <- Encontra a rota que bate com método + path
  │  - Executa middlewares registrados pro prefixo
  │  - Extrai parâmetros da URL (/admin/viacoes/{id} -> $id)
  │  - Instancia o Controller e chama o método
  │
  ▼
src/Controllers/            <- Recebe o request, delega, devolve resposta
  │  - Lê $_GET / $_POST / $_FILES
  │  - Valida entrada via Request + Validator
  │  - Chama o Service (nunca acessa o banco diretamente)
  │  - Chama View::render() ou View::redirect()
  │
  ▼
src/Services/               <- Regras de negócio e coordenação
  │  - Abre transação (beginTransaction)
  │  - Chama Repository para persistir
  │  - Registra histórico (HistoricoRepository)
  │  - Commit ou rollback
  │
  ▼
src/Repositories/           <- SQL puro com PDO prepared statements
  │  - Monta queries com filtros dinâmicos
  │  - Mapeia resultados para Models
  │
  ▼
src/Models/                 <- Objetos tipados (fromRow, getters, toArray)
  │
  ▼
src/views/                  <- Templates PHP com layout
     - Layout injeta $content da view específica
     - htmlspecialchars() em todo output dinâmico (anti-XSS)
```

**Por que sem framework?**
Para você ver cada peça funcionando antes de deixar o Laravel fazer tudo automaticamente. 
Quando você ler o código do Laravel depois, vai reconhecer os mesmos padrões: Router, Middleware, Controller, Service, Repository, Model.

---

## Estrutura do projeto

```text
php-task-app/
├── docker-compose.yml
├── docker-compose.override.yml  <- lê variáveis do .env em dev
├── Dockerfile
├── composer.json
├── phpunit.xml
├── requests.http                <- chamadas de API prontas (REST Client)
│
├── tests/
│   ├── Support/
│   │   └── DatabaseFactory.php  <- cria SQLite em memória pro teste
│   ├── Unit/
│   │   ├── ViacaoModelTest.php
│   │   ├── ViacaoValidatorTest.php
│   │   ├── HistoricoModelTest.php
│   │   └── ViewCsrfTest.php
│   └── Feature/
│       ├── ViacaoServiceTest.php
│       └── HistoricoRepositoryTest.php
│
└── src/
    ├── public/                  <- docroot do Apache
    │   ├── index.php            <- front controller web
    │   ├── api.php              <- front controller da API (sem sessão)
    │   ├── app.css              <- estilos compartilhados (tipografia, tabela)
    │   ├── admin.css            <- estilos do painel admin
    │   ├── home.css             <- estilos do layout público
    │   └── favicon.ico
    │
    ├── Core/
    │   ├── Router.php           <- GET/POST/PUT/DELETE, params, middleware
    │   └── View.php             <- render, flash, redirect, csrfField, methodField
    │
    ├── Http/
    │   ├── Request.php          <- encapsula $_POST + $_FILES + Validator
    │   └── ValidationException.php
    │
    ├── Middleware/
    │   ├── AuthMiddleware.php   <- redireciona pro login se não autenticado
    │   └── CsrfMiddleware.php   <- valida token em POST/PUT/DELETE/PATCH web
    │
    ├── Controllers/
    │   ├── AuthController.php
    │   ├── HistoricoController.php
    │   ├── HomeController.php
    │   ├── UploadController.php
    │   ├── UsuariosController.php
    │   ├── ViacaoController.php
    │   └── Api/
    │       └── ViacaoApiController.php
    │
    ├── Services/
    │   ├── AuthService.php
    │   ├── HistoricoService.php
    │   ├── UploadService.php
    │   └── ViacaoService.php
    │
    ├── Repositories/
    │   ├── HistoricoRepository.php  <- filtros dinâmicos (acao, data, busca livre)
    │   └── UsuarioRepository.php
    │
    ├── Models/
    │   ├── Historico.php
    │   ├── Usuario.php
    │   └── Viacao.php
    │
    ├── Validators/
    │   └── ViacaoValidator.php
    │
    ├── views/
    │   ├── _layout.php              <- layout admin
    │   ├── _layout_public.php       <- layout público (home, login)
    │   ├── auth/
    │   │   └── login.php
    │   ├── home/
    │   │   └── index.php
    │   └── admin/
    │       ├── viacoes/
    │       │   ├── index.php        <- lista com filtros
    │       │   ├── create.php
    │       │   └── edit.php
    │       ├── historico/
    │       │   └── index.php        <- logs com filtros (ação, data, busca)
    │       └── usuarios/
    │           └── index.php
    │
    ├── routes/
    │   ├── web.php                  <- rotas HTML (GET/POST/PUT/DELETE)
    │   └── api.php                  <- rotas JSON
    │
    ├── database/
    │   ├── db.php                   <- getPdo() singleton com lazy connect
    │   └── init.sql                 <- schema das tabelas (roda no boot do MySQL)
    │
    └── cli/
        ├── seed.php                 <- popula usuários, viações e logs de demo
        ├── import_viacoes.php       <- importa viações de um JSON
        └── viacao_data.json         <- JSON de exemplo para o import
```

---

## Painel admin

Após o login, você cai em `/admin/viacoes`. As seções disponíveis:

| Página | URL | O que você vê |
|---|---|---|
| Lista de viações | `/admin/viacoes` | Tabela com filtro por nome/cidade e status (ativa/inativa) |
| Cadastro | `/admin/viacoes/create` | Formulário com upload de logo |
| Edição | `/admin/viacoes/{id}/edit` | Formulário + link pro histórico da viação |
| Histórico | `/admin/historico` | Logs com busca unificada, filtro por ação e por data |
| Usuários | `/admin/usuarios` | Lista com filtro por nome/e-mail |

O histórico mostra only o que realmente mudou (diff-only): se você editar só o nome, o log registra `before: {nome: "antigo"}` e `after: {nome: "novo"}`, sem repetir os campos que não foram alterados.

---

## Rotas web

Forms HTML só enviam `GET` e `POST`. Para usar `PUT` e `DELETE` de forma semântica, os formulários incluem um campo oculto `<input name="_method" value="PUT">` e o `index.php` reescreve o método antes de despachar. Veja `src/public/index.php`.

| Método | Rota | Acesso | Descrição |
|--------|------|--------|-----------|
| GET | `/` | público | Home com viações ativas |
| GET | `/login` | público | Formulário de login |
| POST | `/login` | público | Autentica e redireciona para `/admin/viacoes` |
| POST | `/logout` | logado | Encerra sessão |
| GET | `/admin/viacoes` | logado | Lista viações (com filtros) |
| GET | `/admin/viacoes/create` | logado | Formulário de cadastro |
| POST | `/admin/viacoes` | logado | Cria viação |
| GET | `/admin/viacoes/{id}/edit` | logado | Formulário de edição |
| PUT | `/admin/viacoes/{id}` | logado | Atualiza viação (via `_method=PUT`) |
| DELETE | `/admin/viacoes/{id}` | logado | Remove viação (via `_method=DELETE`) |
| GET | `/admin/historico` | logado | Histórico de alterações |
| GET | `/admin/usuarios` | logado | Lista de usuários |
| GET | `/uploads/{filename}` | público | Serve logos armazenados fora do docroot |

---

## API REST

Todos os endpoints de leitura são públicos. Escrita exige o header `X-API-TOKEN`.
O token é definido na variável de ambiente `API_TOKEN` (veja `.env`).

| Método | Rota | Token? | Descrição |
|--------|------|--------|-----------|
| GET | `/api` | não | Alias para `/api/viacoes` |
| GET | `/api/viacoes` | não | Lista todas as viações |
| GET | `/api/viacoes/{id}` | não | Detalhes de uma viação |
| POST | `/api/viacoes` | sim | Cria viação |
| PUT | `/api/viacoes/{id}` | sim | Atualiza viação |
| DELETE | `/api/viacoes/{id}` | sim | Remove viação |

```bash
# Leitura - sem token
curl -s http://localhost:8081/api/viacoes | jq

# Criação - com token
curl -s -X POST http://localhost:8081/api/viacoes \
  -H 'Content-Type: application/json' \
  -H 'X-API-TOKEN: changeme' \
  -d '{"nome":"Nova Viação","cidade":"Florianópolis","ativa":true}'

# Atualização - com token
curl -s -X PUT http://localhost:8081/api/viacoes/1 \
  -H 'Content-Type: application/json' \
  -H 'X-API-TOKEN: changeme' \
  -d '{"nome":"Nome Corrigido","cidade":"Florianópolis","ativa":true}'
```

O arquivo [requests.http](requests.http) tem todas as chamadas prontas para o PhpStorm.

---

## CLI scripts

### `seed.php`: popula o banco com dados de desenvolvimento

Cria o usuário admin, sete viações e logs de histórico de demo.
Seguro rodar mais de uma vez, registros já existentes são pulados.

```bash
docker compose exec viacoes_php_demo_app php src/cli/seed.php
```

Saída esperada (primeira execução):

```
--- Usuários ---
Criado:          admin@admin.com

--- Viações ---
Criada:          Expresso Guanabara (Rio de Janeiro)
Criada:          Eucatur (Curitiba)
Criada:          Reunidas Paulista (São Paulo)
Criada:          Cometa (Campinas)
Criada:          Itapemirim (Vitória)
Criada:          Real Expresso (Brasília)
Criada:          Penha (Belo Horizonte)

--- Histórico ---
Log Criado:      Expresso Guanabara
Log Criado:      Eucatur
Log Criado:      Reunidas Paulista
Log Criado:      Cometa
Log Criado:      Itapemirim
Log Criado:      Real Expresso
Log Criado:      Penha
Log Editado:     Cometa
Log Editado:     Penha

Seed concluído.
```

Saída na segunda execução (tudo pulado):

```
--- Usuários ---
Já existe:       admin@admin.com

--- Viações ---
Já existe:       Expresso Guanabara
...

--- Histórico ---
(nenhum log novo, viações já existiam)

Seed concluído.
```

### `import_viacoes.php` - importa viações de um arquivo JSON

```bash
docker compose exec viacoes_php_demo_app \
  php src/cli/import_viacoes.php src/cli/viacao_data.json
```

O arquivo `viacao_data.json` já tem exemplos, incluindo entradas inválidas (nome vazio, cidade vazia) para ver o comportamento de validação.

Saída esperada:
```
Import complete: created=5, skipped=2
Errors:
...
```

Formato aceito:

```json
[
  { "nome": "Minha Viação", "cidade": "São Paulo", "ativa": true },
  { "nome": "Outra",        "cidade": "Curitiba",  "ativa": false }
]
```

---

## Testes

O projeto tem uma suite de testes PHPUnit.
Os testes de feature usam **SQLite em memória** sem Docker, sem MySQL.

```bash
# Rodar localmente (PHP instalado na máquina)
php vendor/bin/phpunit

# Roda com mais detalhes sobre os testes, como o nome
php vendor/bin/phpunit --testdox

# Rodar dentro do container
docker compose exec viacoes_php_demo_app php vendor/bin/phpunit
```

### O que está coberto

**Unit tests** (`tests/Unit/`) - testam uma classe isolada, sem banco:

| Arquivo | O que testa |
|---|---|
| `ViacaoModelTest.php` | `fromRow()`: conversão de tipos (id, ativa, logo, datas) |
| `ViacaoValidatorTest.php` | Validação de nome, cidade, ativa (string, bool, ausente) |
| `HistoricoModelTest.php` | `getBefore()` / `getAfter()`, JSON inválido, campos nulos |
| `ViewCsrfTest.php` | Geração de token, `csrfField()`, flash messages |

**Feature tests** (`tests/Feature/`) - testam fluxos reais com banco SQLite:

| Arquivo | O que testa |
|---|---|
| `ViacaoServiceTest.php` | CRUD completo, histórico gravado, rollback em falha |
| `HistoricoRepositoryTest.php` | Filtros (viacao_id, acao, data, busca livre por viação/usuário/conteúdo) |

### Como o banco de teste funciona

`tests/Support/DatabaseFactory.php` cria um PDO SQLite em memória e roda um schema compatível com o MySQL (sem `ENGINE=InnoDB`, sem `ON UPDATE CURRENT_TIMESTAMP`).
Cada teste começa com o banco limpo - sem dados do ambiente de desenvolvimento.

```php
// Exemplo: injetar o PDO de teste no lugar do MySQL real
$pdo     = DatabaseFactory::create();
$service = new ViacaoService($pdo);
```

---

## Docker: quando reconstruir a imagem

Mudanças em **arquivos PHP, CSS e views** refletem automaticamente no browser, o volume monta o diretório local dentro do container em tempo real.

Você precisa reconstruir (`--build`) apenas quando alterar o **Dockerfile**:

```bash
docker compose up --build -d
```

Casos comuns que exigem rebuild:
- Instalar uma extensão PHP nova (ex: `pdo_sqlite`)
- Alterar configurações no php.ini, por exemplo
- Mudar a versão do PHP ou do Apache

### `docker-compose.override.yml`

O arquivo `docker-compose.override.yml` lê as variáveis de ambiente do `.env`
e injeta nos containers. O Docker Compose carrega este arquivo automaticamente
junto com o `docker-compose.yml` quando você roda `docker compose up`.

Normalmente um override de dev ficaria no `.gitignore` (junto com o `.env`),
porque cada desenvolvedor pode ter configurações locais diferentes. Neste projeto
ele está commitado **por fins didáticos**, o `.gitignore` tem a entrada comentada
e explicada para que os estagiários entendam o padrão usual.

> **Atenção:** nunca commite o arquivo `.env` com senhas reais. Use `.env.example`
> para compartilhar as chaves necessárias sem expor os valores. Em CI/CD,
> configure secrets no ambiente (GitHub Actions, GitLab CI) em vez de usar `.env`.

### Carregamento de CSS

Os layouts usam `filemtime()` pra gerar uma versão baseada na data de modificação:
```
/app.css?v=1748000000
```
Quando você salva o arquivo, o timestamp muda, o browser entende que é um
recurso novo e baixa o CSS atualizado, sem precisar de Ctrl+Shift+R.

---

## Banco de dados

As tabelas são criadas automaticamente pelo `init.sql` no primeiro boot do MySQL.
**O init.sql só define o schema**, os dados de desenvolvimento ficam no `seed.php`.

Tabelas:

| Tabela | Descrição |
|---|---|
| `usuarios` | Usuários com senha bcrypt, email único |
| `viacoes` | Empresas de ônibus com logo opcional |
| `viacoes_historico` | Log de criação/edição/exclusão com diff em JSON |

Para recriar o banco do zero:

```bash
docker compose down -v       # apaga volumes (banco incluído)
docker compose up --build -d # sobe e roda init.sql automaticamente
docker compose exec viacoes_php_demo_app php src/cli/seed.php
```

---

## PSR-4 e autoload

Namespaces usam o prefixo `App\`, mapeado para `src/` no `composer.json`:

```json
"autoload": {
  "psr-4": { "App\\": "src/" }
},
"autoload-dev": {
  "psr-4": { "Tests\\": "tests/" }
}
```

Após mover arquivos ou criar novas classes, regenere o autoload:

```bash
docker compose exec viacoes_php_demo_app composer dump-autoload
```

---

## Próximos passos sugeridos

- Adicionar paginação no histórico e na lista de viações (o `HistoricoRepository` já tem a estrutura de filtros pronta)
- Criar um endpoint de busca na API (`GET /api/viacoes?q=guanabara`)
- Implementar permissões por papel (ex: admin vs. operador)
- Adicionar upload de logo pela API (multipart)
- Experimentar o mesmo projeto em Laravel e comparar o que o framework elimina
- Escrever testes para os Controllers (testar o fluxo HTTP completo)
