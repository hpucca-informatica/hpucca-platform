<?php

declare(strict_types=1);

use HPucca\Platform\Models\User;

$managedUser = $managedUser instanceof User ? $managedUser : null;
$action = '/admin/users/' . ($managedUser?->id ?? '');
$submitLabel = 'Salvar alteracoes';
$readonlyCode = $managedUser?->code ?? '';
$includePassword = false;
require __DIR__ . '/_form.php';
