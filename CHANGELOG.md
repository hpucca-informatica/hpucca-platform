# Changelog

Todas as mudancas relevantes deste projeto serao documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), e este projeto segue versionamento semantico a partir da versao `0.1.0`.

## [Unreleased]

### Adicionado

- Acabamento administrativo do Sprint 5.1.1 para fontes de integracao e eventos, sem alteracao de banco, API, autenticacao ou regras de ingestao.
- Geracao automatica de slug nas telas de fontes de integracao, mantendo edicao manual e validacao backend.
- Componente reutilizavel de copia para codigos publicos, JSON de eventos e API key de exibicao unica.
- Badges de status, estados vazios e formatacao de datas administrativas em `dd/mm/yyyy HH:mm:ss`.
- Configuracao `APP_TIMEZONE` para exibicao de datas administrativas.
- Primeira camada de entrada e fila de eventos da plataforma.
- Migrations `004_create_integration_sources.sql` e `005_create_events.sql`.
- Codigos publicos imutaveis `SRC000001` e `EVT000001` gerados por sequences PostgreSQL.
- CRUD administrativo de fontes de integracao por tenant.
- Listagem e visualizacao administrativa de eventos.
- Endpoint publico `POST /api/v1/events` com autenticacao por `X-API-Key`.
- `ApiKeyService` com geracao por `random_bytes()`, prefixo `hpk_live_`, armazenamento por `password_hash()` e verificacao segura.
- `EventIngestionService` para autenticacao da fonte, validacao do payload, limite de tamanho, idempotencia e persistencia de eventos `pending`.
- Repositories e models para `IntegrationSource` e `Event`.
- Menu administrativo de Automacao visivel somente para usuarios `owner`.
- Testes de regressao para ingestao de eventos, API key, idempotencia, CSRF administrativo e migrations de imutabilidade.
- CRUD administrativo de usuarios usando a tabela `users`.
- Listagem, busca, filtro por empresa, paginacao, cadastro, edicao, detalhe, ativacao e inativacao de usuarios.
- Redefinicao administrativa de senha para owners.
- Perfil funcional para alterar nome e email do proprio usuario.
- Alteracao da propria senha com validacao da senha atual.
- Protecao contra inativacao ou rebaixamento do ultimo owner ativo do tenant.
- Protecao contra inativacao do proprio usuario owner.
- Bloqueio de ativacao de usuarios vinculados a empresa inativa.
- CRUD administrativo de empresas usando a tabela `tenants`.
- Listagem, busca, paginacao, cadastro, edicao, detalhe, ativacao e inativacao de empresas.
- Migration `003_add_tenant_code.sql` com codigo publico de tenant no formato `TEN000001`.
- Sequence PostgreSQL `tenants_code_seq` para geracao segura de codigos publicos de empresas.
- Trigger PostgreSQL para impedir alteracao direta de `tenants.code`.
- `CompanyRepository`, `CompanyService` e contrato de repositorio para testes.
- `OwnerMiddleware` provisório para restringir administracao de empresas a usuarios `owner`.
- `CsrfService` reutilizavel para formularios POST administrativos e logout.
- `FlashService` reutilizavel para mensagens de interface.
- Layout administrativo reutilizavel para paginas autenticadas.
- Menu lateral com Dashboard, Cadastros e Sistema.
- Cabecalho administrativo com usuario e empresa autenticados.
- Dashboard reorganizado em cards e acesso rapido.
- Paginas placeholder protegidas para empresas, usuarios, perfil e alteracao de senha.
- CSS e JavaScript administrativos leves, sem dependencia de CDN.
- Fundacao de autenticacao por sessao PHP.
- Autenticacao por tenant, login e senha.
- Migration `002_create_users.sql` com `users`.
- Codigo publico imutavel de usuario no formato `USR000001`.
- Sequence PostgreSQL `users_code_seq` para geracao segura de codigos publicos.
- Models `Tenant` e `User`.
- Repositories `TenantRepository` e `UserRepository`.
- Services `AuthService`, `UserService` e `PublicCodeGenerator`.
- Controllers `AuthController` e `DashboardController`.
- Middleware `AuthMiddleware`.
- Views `login.php` e `dashboard.php`.
- Rotas `GET /login`, `POST /login`, `POST /logout` e `GET /dashboard`.

### Decisoes

- Eventos duplicados retornam HTTP 200 com `status = duplicate` e o `event_code` existente para permitir reenvio idempotente.
- API keys sao armazenadas com `password_hash()` para manter armazenamento unidirecional e permitir upgrades futuros de algoritmo.
- Webhook publico nao usa sessao nem CSRF; a autenticacao e feita por API key da fonte.
- O payload recebido e limitado a 65536 bytes e apenas o objeto `data` e persistido em `events.payload`.
- Assinatura HMAC, rotacao de API key, processamento, retries, worker e envio ao n8n permanecem fora do Sprint 5.1.
- Inativacao substitui exclusao fisica de empresas.
- A protecao `owner` e provisoria e nao substitui roles e permissions futuros.
- Com multiplos tenants ja existentes, a atribuicao inicial de `TEN000001`, `TEN000002` etc. nao possui ordem de negocio deterministica; no ambiente atual ha apenas um tenant, entao HPucca Informatica recebera `TEN000001`.
- O CRUD administrativo de usuarios tambem usa `type = owner` como autorizacao provisoria; roles e permissions completos continuam fora do escopo.
- Usuario nao possui exclusao fisica; status `inactive` substitui remocao.
- E-mail e opcional e usado apenas para contato, notificacoes e futura recuperacao de senha.
- Login e unico por tenant, nao global.
- E-mail, quando informado, e unico por tenant.
- Categorias iniciais de usuario: `owner`, `admin`, `manager` e `user`.
- `type` nao substitui o futuro sistema de roles e permissions.

## [0.2.0] - 2026-07-24

### Adicionado

- Dependencia `vlucas/phpdotenv` para carregamento de `.env`.
- Bootstrap compartilhado para inicializacao de ambiente.
- Classe `Config` para acesso a configuracoes por chave pontuada.
- Arquivos `config/app.php` e `config/database.php`.
- Classe `Database` com conexao PDO PostgreSQL lazy.
- Status de banco no endpoint `GET /api/v1/health`.
- Endpoint `GET /api/v1/health/database`.
- Runner `bin/migrate.php` para migrations SQL sem rollback.
- Migration `001_create_tenants.sql` com tabela `tenants`.

### Alterado

- `Dockerfile` instala a extensao `pdo_pgsql`.
- Documentacao atualizada para variaveis de ambiente, PostgreSQL, migrations e health checks.

## [0.1.0] - 2026-07-21

### Adicionado

- Estrutura HTTP minima da HPucca Platform.
- Classes `Router`, `Request` e `Response`.
- Endpoint `GET /api/v1/health`.
- `Dockerfile` com PHP 8.3 e Apache.
- Preparacao de deploy no EasyPanel.
- Integracao operacional entre GitHub, Codex e EasyPanel.
- Configuracao Front Controller do Apache.
