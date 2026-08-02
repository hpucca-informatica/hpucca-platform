<?php

declare(strict_types=1);

$message = isset($message) && is_string($message) ? $message : 'Modulo sera implementado nas proximas etapas.';
?>
<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Modulo em preparacao</p>
            <h2><?= $e($title ?? 'Modulo') ?></h2>
        </div>
    </div>
    <p class="placeholder-message"><?= $e($message) ?></p>
    <div class="table-preview" aria-label="Tabela preparada para etapas futuras">
        <div></div>
        <div></div>
        <div></div>
    </div>
</section>
