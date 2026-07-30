<?php

declare(strict_types=1);

use HPucca\Platform\Core\Config;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

assert(Config::get('app.name') === 'HPucca Platform');
assert(Config::get('app.version') === '0.2.0');
assert(Config::get('database.host') === '');
assert(Config::get('missing.value', 'fallback') === 'fallback');

echo 'ConfigTest passed.' . PHP_EOL;
