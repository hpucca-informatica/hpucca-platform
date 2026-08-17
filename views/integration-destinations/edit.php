<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationDestination;

$destination = $destination instanceof IntegrationDestination ? $destination : null;
$action = '/admin/integration-destinations/' . ($destination?->id ?? '');
$submitLabel = 'Salvar alteracoes';
require __DIR__ . '/_form.php';
