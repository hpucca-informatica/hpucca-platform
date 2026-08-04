<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationSource;

$source = $source instanceof IntegrationSource ? $source : null;
$action = '/admin/integration-sources/' . ($source?->id ?? '');
$submitLabel = 'Salvar alteracoes';
require __DIR__ . '/_form.php';
