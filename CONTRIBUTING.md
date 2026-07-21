# Guia de Contribuicao

Este projeto segue convencoes simples para manter o historico claro, revisavel e seguro.

## Commits

Use o padrao Conventional Commits.

Formato:

```text
tipo(escopo): descricao
```

Tipos permitidos:

- `feat`
- `fix`
- `build`
- `docs`
- `test`
- `refactor`
- `chore`
- `security`

Escopos iniciais:

- `core`
- `api`
- `router`
- `config`
- `database`
- `events`
- `dispatcher`
- `auth`
- `tenant`
- `integrations`
- `docker`
- `deploy`
- `docs`
- `repo`

Regras:

- Faca um commit por alteracao logica.
- Escreva mensagens de commit em portugues.
- Use verbo no infinitivo.
- Nao termine a descricao com ponto final.

Exemplos:

```text
docs(repo): adicionar guia de contribuicao
fix(deploy): corrigir front controller do Apache
build(docker): preparar imagem para EasyPanel
```

## Branches

A branch `main` representa a versao estavel do projeto.

Use a seguinte nomenclatura:

```text
feature/nome
fix/nome
refactor/nome
docs/nome
```

## Revisao

Antes de fazer commit e push:

- Revise os arquivos alterados.
- Confira se a alteracao esta restrita ao objetivo.
- Execute os testes ou validacoes aplicaveis.
- Verifique se nao ha alteracoes acidentais.

## Seguranca

E proibido versionar segredos, tokens, senhas, chaves privadas ou arquivos `.env`.

Use `.env.example` para documentar variaveis de ambiente sem valores sensiveis.
