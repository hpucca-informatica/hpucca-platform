<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;

$event = $event instanceof Event ? $event : null;
$prettyPayload = '';

if ($event instanceof Event) {
    $decoded = json_decode($event->payload, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $encodedPayload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $prettyPayload = is_string($encodedPayload) ? $encodedPayload : $event->payload;
    } else {
        $prettyPayload = $event->payload;
    }
}
?>
<?php if ($event instanceof Event): ?>
    <section class="entity-details">
        <dl>
            <dt>Codigo</dt>
            <dd><?= $copyableText($event->code, 'Copiar', 'Codigo copiado', 'code-copy') ?></dd>
            <dt>Empresa</dt>
            <dd><?= $e($event->tenantName) ?></dd>
            <dt>Fonte</dt>
            <dd><?= $e($event->integrationSourceName) ?></dd>
            <dt>Tipo</dt>
            <dd><?= $e($event->eventType) ?></dd>
            <dt>External ID</dt>
            <dd><?= $e($event->externalId) ?></dd>
            <dt>Status</dt>
            <dd><?= $statusBadge($event->status) ?></dd>
            <dt>Tentativas</dt>
            <dd><?= $e((string) $event->attempts) ?></dd>
            <dt>Disponivel em</dt>
            <dd><?= $e($formatDate($event->availableAt)) ?></dd>
            <dt>Ocorrido em</dt>
            <dd><?= $e($formatDate($event->occurredAt)) ?></dd>
            <dt>Recebido em</dt>
            <dd><?= $e($formatDate($event->receivedAt)) ?></dd>
            <dt>Processado em</dt>
            <dd><?= $e($formatDate($event->processedAt)) ?></dd>
            <dt>Falhou em</dt>
            <dd><?= $e($formatDate($event->failedAt)) ?></dd>
            <dt>Ultimo erro</dt>
            <dd><?= $e($event->lastError ?? '-') ?></dd>
        </dl>
    </section>

    <section class="json-viewer">
        <div class="section-header">
            <h2>Payload</h2>
            <button type="button" class="button-small copy-button" data-copy-target="#event-payload-json" data-copy-feedback="JSON copiado" aria-label="Copiar JSON">Copiar JSON</button>
        </div>
        <pre id="event-payload-json"><code><?= $e($prettyPayload) ?></code></pre>
        <span class="copy-feedback" data-copy-status aria-live="polite"></span>
    </section>

    <div class="form-actions">
        <a href="/admin/events">Voltar</a>
    </div>
<?php endif; ?>
