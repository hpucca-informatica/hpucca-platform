<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;

$event = $event instanceof Event ? $event : null;
$prettyPayload = '';

if ($event instanceof Event) {
    $decoded = json_decode($event->payload, true);
    $prettyPayload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $prettyPayload = is_string($prettyPayload) ? $prettyPayload : $event->payload;
}
?>
<?php if ($event instanceof Event): ?>
    <section class="entity-details">
        <dl>
            <dt>Codigo</dt>
            <dd><?= $e($event->code) ?></dd>
            <dt>Empresa</dt>
            <dd><?= $e($event->tenantName) ?></dd>
            <dt>Fonte</dt>
            <dd><?= $e($event->integrationSourceName) ?></dd>
            <dt>Tipo</dt>
            <dd><?= $e($event->eventType) ?></dd>
            <dt>External ID</dt>
            <dd><?= $e($event->externalId) ?></dd>
            <dt>Status</dt>
            <dd><?= $e($event->status) ?></dd>
            <dt>Tentativas</dt>
            <dd><?= $e((string) $event->attempts) ?></dd>
            <dt>Disponivel em</dt>
            <dd><?= $e($event->availableAt) ?></dd>
            <dt>Ocorrido em</dt>
            <dd><?= $e($event->occurredAt ?? '-') ?></dd>
            <dt>Recebido em</dt>
            <dd><?= $e($event->receivedAt) ?></dd>
            <dt>Processado em</dt>
            <dd><?= $e($event->processedAt ?? '-') ?></dd>
            <dt>Falhou em</dt>
            <dd><?= $e($event->failedAt ?? '-') ?></dd>
            <dt>Ultimo erro</dt>
            <dd><?= $e($event->lastError ?? '-') ?></dd>
        </dl>
    </section>

    <section class="entity-details">
        <h2>Payload</h2>
        <pre><?= $e($prettyPayload) ?></pre>
    </section>

    <div class="form-actions">
        <a href="/admin/events">Voltar</a>
    </div>
<?php endif; ?>
