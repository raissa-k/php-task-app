# Task App (mini Laravel-like em PHP)

Projeto de referência para estagiários praticarem uma arquitetura mais organizada antes de entrar em Laravel.

Fluxo principal do app:

- Front Controller (`src/public/index.php`)
- Router (`src/Core/Router.php`)
- Controller (`src/Controllers/TaskController.php`)
- Service (`src/Services/TaskService.php`)
- Model/DTO (`src/Models/Task.php`)
- Views (`src/views/*.php`)

## Objetivo didático

Este projeto demonstra, de forma pequena e funcional:

- Roteamento com parâmetros (`/tasks/{id}/edit`)
- Separação de responsabilidades por camadas
- CRUD completo com MySQL
- Flash message e padrão PRG (Post/Redirect/Get)
- Estrutura compatível com PSR-4 para reduzir warnings de IDE

## Stack

- PHP 8.4 + Apache (Docker)
- MySQL 8
- Composer 2 (no container) para autoload PSR-4

## Estrutura do projeto

```text
task-app/
	docker-compose.yml
	Dockerfile
	composer.json

	src/
		public/
			index.php
			app.css

		Core/
			Router.php
			View.php

		Controllers/
			TaskController.php

		Services/
			TaskService.php

		Models/
			Task.php

		views/
			_layout.php
			index.php
			create.php
			edit.php

		routes/
			web.php

		database/
			db.php
			init.sql
```

## Como rodar

1. Suba os containers:

```bash
cd task-app
docker compose up --build -d
```

Na inicializacao do container `app`, o Composer gera `vendor/autoload.php` automaticamente.

2. Acesse:

- http://localhost:8081/tasks

3. Para parar:

```bash
docker compose down
```

## Rotas

- `GET /` -> lista tasks
- `GET /tasks` -> lista tasks
- `GET /tasks/create` -> formulário de criação
- `POST /tasks` -> cria task
- `GET /tasks/{id}/edit` -> formulário de edição
- `POST /tasks/{id}` -> atualiza task
- `POST /tasks/{id}/delete` -> remove task

## Banco de dados

- O MySQL é iniciado via Docker Compose.
- A tabela `tasks` é criada automaticamente no primeiro start pelo `src/database/init.sql`.

Reset completo (recria banco e dados seed):

```bash
docker compose down -v
docker compose up --build -d
```

## PSR-4 e IDE

Para reduzir mensagens como "Namespace name doesn't match the PSR-0/PSR-4 project structure":

- Namespaces usam `App\\...`
- Estrutura de classes está em `src/Core`, `src/Controllers`, `src/Services`, `src/Models`
- `composer.json` define:

```json
"autoload": {
	"psr-4": {
		"App\\": "src/"
	}
}
```

Apos mudancas de namespace/estrutura, rode:

```bash
composer dump-autoload --working-dir=.
```

## Próximos passos sugeridos para estudo

- Adicionar validação mais robusta (camada dedicada)
- Introduzir método HTTP `PUT/DELETE` com spoofing de `_method`
- Criar testes de integração para o fluxo de rotas
- Extrair camada de repositório para separar SQL da regra de negócio
