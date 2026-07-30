# Changelog

Todas as mudancas relevantes deste projeto serao documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), e este projeto segue versionamento semantico a partir da versao `0.1.0`.

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
