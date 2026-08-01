<?php

declare(strict_types=1);

use HPucca\Platform\Core\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

require dirname(__DIR__) . '/bootstrap/app.php';

assert(Config::get('app.name') === 'HPucca Platform');
assert(Config::get('app.version') === '0.2.0');
assert(Config::get('database.host') === '');
assert(Config::get('missing.value', 'fallback') === 'fallback');

echo 'ConfigTest passed.' . PHP_EOL;
