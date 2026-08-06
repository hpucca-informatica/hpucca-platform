<?php

declare(strict_types=1);

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Repositories\EventRepository;
use HPucca\Platform\Services\EventDispatcher;
use HPucca\Platform\Services\SimulatedEventProcessor;

$usage = 'Invalid arguments.' . PHP_EOL . PHP_EOL
    . 'Usage:' . PHP_EOL
    . 'php bin/dispatch-events.php [--limit=1]' . PHP_EOL;
$arguments = array_slice($argv, 1);

if ($arguments !== [] && $arguments !== ['--limit=1']) {
    fwrite(STDERR, $usage);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $dispatcher = new EventDispatcher(
        new EventRepository((new Database(Config::get('database')))->connection()),
        new SimulatedEventProcessor(),
    );
    $result = $dispatcher->dispatchOnce();

    if ($result['status'] === 'idle') {
        echo 'No pending event available.' . PHP_EOL;
        exit(0);
    }

    $event = $result['event'];
    $eventCode = $event === null ? 'unknown' : $event->code;

    if ($result['status'] === 'processed') {
        echo sprintf('Processed event %s.%s', $eventCode, PHP_EOL);
        exit(0);
    }

    echo sprintf('Failed event %s.%s', $eventCode, PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Event dispatcher failed.' . PHP_EOL);
    exit(1);
}
