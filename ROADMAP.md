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

- historico detalhado de execucoes;
- preparacao para destino real.

## Sprint 6.3 - Execucao Automatica Segura do Dispatcher

O Sprint 6.3 torna o dispatcher adequado para agendamento operacional com cron. O comando `php bin/dispatch-events.php` passa a executar lotes limitados, com `--limit=N`, defaults seguros e saida resumida.

O scheduler usa PostgreSQL advisory lock para impedir duas execucoes globais simultaneas. O `SKIP LOCKED` permanece no repository porque ele resolve outro nivel do problema: concorrencia na reserva de cada evento.

Antes do lote, eventos antigos em `processing` sao recuperados para `pending` quando ultrapassam `EVENT_PROCESSING_TIMEOUT_MINUTES`. Isso cobre o caso em que o processo morre depois da reserva. Eventos recentes em `processing` nao sao tocados, e `attempts` nao diminui.

Configuracoes:

- `EVENT_DISPATCH_LIMIT_DEFAULT=10`;
- `EVENT_DISPATCH_LIMIT_MAX=100`;
- `EVENT_PROCESSING_TIMEOUT_MINUTES=15`.

Cron sugerido inicialmente:

```bash
php /var/www/html/bin/dispatch-events.php --limit=25
```

Uma vez por minuto e suficiente no inicio porque cada execucao e curta, limitada e protegida por lock. Isso nao cria worker continuo: o processo inicia, processa ate o limite ou ate esvaziar a fila, e termina.

Fora deste Sprint:

- n8n;
- HTTP client;
- WhatsApp;
- retry automatico com backoff;
- max attempts;
- dead-letter queue;
- worker continuo;
- supervisor;
- Redis, RabbitMQ, Kafka ou SQS;
- dashboard de metricas;
- processamento via navegador.

## Sprint 6.4 - Retry Inteligente e Conservador

O Sprint 6.4 adiciona retry automatico somente para falhas transientes conhecidas durante a execucao do Dispatcher. A politica usa `EVENT_RETRY_MAX_ATTEMPTS` e `EVENT_RETRY_DELAYS_SECONDS`.

Fluxo transiente:

- `pending -> processing`;
- falha transiente com tentativas restantes: `processing -> pending`;
- `available_at` recebe o horario da proxima tentativa;
- a proxima reserva incrementa `attempts`;
- ao atingir o maximo configurado, a falha transiente vira `failed`.

Falhas permanentes e excecoes inesperadas nao entram em retry automatico. Elas passam para `failed` com mensagem sanitizada e sem payload, segredo, SQL ou stack trace.

O retry automatico nao substitui o reprocessamento manual. O reprocessamento manual continua sendo uma acao HTTP owner-only para eventos ja `failed`; o retry automatico acontece apenas no CLI, durante o processamento de uma falha transiente.

Fora deste Sprint:

- HTTP client externo;
- n8n;
- WhatsApp;
- dead-letter queue;
- historico detalhado de tentativas;
- worker continuo;
- supervisor;
- Redis, RabbitMQ, Kafka ou SQS;
- dashboard de metricas;
- migration.

## Sprint 6.5 - Observabilidade Operacional de Eventos

O Sprint 6.5 cria uma visao operacional simples do pipeline de eventos em `GET /admin/automation`, restrita a usuarios `owner`. A tela usa somente PostgreSQL, repositories, service e views administrativas existentes. Nao ha infraestrutura externa de observabilidade.

Metricas principais:

- eventos recebidos no periodo;
- processados;
- falhos;
- pendentes;
- em `processing`;
- em retry agendado;
- taxa de sucesso;
- attempts medio dos eventos finalizados.

Definicoes:

- Periodo usa `received_at` como referencia principal.
- Retry agendado e `status = pending AND attempts > 0 AND available_at > CURRENT_TIMESTAMP`.
- Taxa de sucesso e `processed / (processed + failed)`, sem pending, processing ou retry no denominador.
- Processing possivelmente stale e calculado por idade de `updated_at` comparada com `EVENT_PROCESSING_TIMEOUT_MINUTES`.

A tela tambem mostra ultimas falhas, proximos retries, eventos em processing e top attempts. Todas as listas sao limitadas e nao exibem payload, API key, hash, stack trace ou credenciais. Filtros por tenant, fonte, `event_type`, status e periodo funcionam de forma combinada por prepared statements.

Fora deste Sprint:

- Grafana, Prometheus, OpenTelemetry, Elastic ou Loki;
- alertas por e-mail, WhatsApp ou notificacoes;
- historico detalhado de tentativas;
- materialized views;
- tabela nova de metricas;
- Redis, filas externas ou worker continuo;
- qualquer acao POST no dashboard.

## Sprint 6.6A - Fundacao de Destinos de Integracao

O Sprint 6.6A prepara a plataforma para processamento externo sem implementar transporte real.

Conceitos:

- `IntegrationSource`: de onde o evento entra.
- `IntegrationDestination`: para onde o evento sera processado.

A migration `006_create_integration_destinations.sql` cria `integration_destinations`, a sequence `integration_destinations_code_seq`, codigos `DST000001`, trigger de imutabilidade de `code`, `type = n8n`, `status = active/inactive`, `config JSONB` nao sensivel e o vinculo nullable `integration_sources.destination_id`.

O vinculo source -> destination e sempre opcional. O service valida que fonte e destino pertencem ao mesmo tenant, impedindo `Source A -> Destination B` entre empresas. Destinos inativos nao aparecem como opcao normal para novo vinculo, mas um vinculo existente com destino inativo continua legivel.

O CRUD de destinos e owner-only:

```http
GET /admin/integration-destinations
GET /admin/integration-destinations/create
POST /admin/integration-destinations
GET /admin/integration-destinations/{id}
GET /admin/integration-destinations/{id}/edit
POST /admin/integration-destinations/{id}
POST /admin/integration-destinations/{id}/activate
POST /admin/integration-destinations/{id}/deactivate
```

`EventProcessorResolver` resolve `type = n8n` para `N8nEventProcessor`, mas o processador n8n e apenas placeholder. Se chamado, ele falha permanentemente com mensagem sanitizada e nao faz HTTP, cURL, webhook, consulta a credential, retry, banco ou mutacao de evento.

Fora deste Sprint:

- HTTP client;
- URL de webhook;
- token, secret, API key, bearer token ou credential vault;
- workflow n8n real;
- WhatsApp;
- multiplos destinos por fonte;
- roteamento por `event_type`;
- fan-out;
- circuit breaker;
- rate limit;
- retry HTTP real;
- filas externas.
