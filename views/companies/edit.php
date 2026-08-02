<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;

$company = $company instanceof Tenant ? $company : null;
$action = '/admin/companies/' . ($company?->id ?? '');
$submitLabel = 'Salvar alteracoes';
$readonlyCode = $company?->code ?? '';
require __DIR__ . '/_form.php';
