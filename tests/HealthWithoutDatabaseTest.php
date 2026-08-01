<?php

declare(strict_types=1);

use HPucca\Platform\Controllers\HealthController;
use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

require dirname(__DIR__) . '/bootstrap/app.php';

$controller = new HealthController(new Database(Config::get('database')));

ob_start();
$controller->show()->send();
$healthBody = ob_get_clean();

assert($healthBody === '{"status":"ok","version":"0.2.0","database":"unavailable"}');

ob_start();
$controller->database()->send();
$databaseBody = ob_get_clean();

assert($databaseBody === '{"status":"error","database":"unavailable"}');

echo 'HealthWithoutDatabaseTest passed.' . PHP_EOL;
