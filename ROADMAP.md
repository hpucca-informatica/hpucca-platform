# HPucca Platform Roadmap

## Sprint 6.1 - Dispatcher Basico de Eventos

O Dispatcher e o primeiro ciclo de processamento da fila de eventos. Ele procura um evento pendente e disponivel, reserva a linha com seguranca, muda o status para `processing`, incrementa `attempts` e executa um processador simulado. Ao final, marca o evento como `processed` ou `failed`.

Fluxo permitido neste Sprint:

- `pending -> processing`;
- `processing -> processed`;
- `processing -> failed`.

Fora deste Sprint:

- reprocessamento manual;
- retry automatico;
- lotes;
- worker continuo;
- cron;
- n8n;
- WhatsApp;
- HTTP client;
- destinos externos;
- dead-letter queue.

## Comando Manual

```bash
php bin/dispatch-events.php
php bin/dispatch-events.php --limit=1
```

Saidas esperadas:

```text
No pending event available.
Processed event EVT000002.
Failed event EVT000003.
```

`0` indica fila vazia ou sucesso. `1` indica falha de processamento ou erro interno do dispatcher.

## Reserva Atomica

O repository usa PostgreSQL `FOR UPDATE OF e SKIP LOCKED` dentro de uma transacao curta:

```sql
SELECT e.id
FROM events e
INNER JOIN tenants t ON t.id = e.tenant_id
INNER JOIN integration_sources s ON s.id = e.integration_source_id
WHERE e.status = 'pending'
  AND e.available_at <= CURRENT_TIMESTAMP
  AND t.status = 'active'
  AND s.status = 'active'
ORDER BY e.available_at ASC, e.id ASC
FOR UPDATE OF e SKIP LOCKED
LIMIT 1
```

A transacao reserva apenas a linha escolhida, atualiza `status = processing`, incrementa `attempts` e termina antes do processador simulado trabalhar. Isso reduz lock contention e evita que duas execucoes processem o mesmo evento.

## Campos de Controle

- `available_at`: define quando o evento pode ser selecionado.
- `attempts`: incrementado na reserva.
- `processed_at`: preenchido em sucesso.
- `failed_at`: preenchido em falha.
- `last_error`: recebe apenas mensagem segura e curta.

## Aprendizado

Nao processar dentro do webhook mantem a entrada rapida e previsivel para sistemas externos. O webhook autentica, valida, aplica idempotencia e persiste `pending`; o processamento acontece depois.

Nao manter transacao aberta durante processamento evita segurar locks enquanto um destino externo ou processador demora, falha ou fica indisponivel.

O estado `processing` torna a reserva explicita. Ele separa evento recebido de evento em execucao e permite observar tentativas sem alterar payload ou codigo publico.

`SKIP LOCKED` evita processamento duplicado em execucoes concorrentes: uma execucao bloqueia a linha escolhida, e outra execucao pula essa linha em vez de esperar ou processar o mesmo evento.

## Sprint 6.2 - Reprocessamento Manual e Observabilidade

O Sprint 6.2 adiciona somente a transicao administrativa `failed -> pending`. Owners podem abrir o detalhe de um evento com falha e recoloca-lo na fila por `POST /admin/events/{id}/reprocess`, com CSRF e validacao de estado tambem no SQL.

O reprocessamento manual nao executa o Dispatcher pela requisicao web. Ele apenas deixa o evento elegivel para uma execucao posterior do CLI, preservando `payload`, `code`, tenant, fonte, `external_id`, `event_type` e `attempts`.

`attempts` continua crescendo porque representa o total historico de tentativas. A observabilidade minima usa os campos existentes: `attempts`, `available_at`, `received_at`, `processed_at`, `failed_at`, `last_error` e `updated_at`.

Fora deste Sprint:

- retry automatico;
- politica de backoff;
- max attempts;
- cron;
- worker continuo;
- tabela de historico;
- dead-letter queue;
- botao processar agora;
- destinos externos.

## Evolucoes Futuras

- stale processing recovery para eventos que ficarem em `processing` se o processo morrer apos a reserva;
- historico detalhado de execucoes;
- politica de retry automatico com limites;
- preparacao para destino real.
