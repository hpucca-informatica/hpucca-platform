<?php

declare(strict_types=1);

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Repositories\EventRepository;
use HPucca\Platform\Services\DispatcherLock;
use HPucca\Platform\Services\EventDispatcher;
use HPucca\Platform\Services\SimulatedEventProcessor;

$arguments = array_slice($argv, 1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

/**
 * @return array{valid: bool, limit: int, message: string}
 */
function dispatchLimit(array $arguments, int $defaultLimit, int $maxLimit): array
{
    $usage = 'Usage:' . PHP_EOL . 'php bin/dispatch-events.php [--limit=N]' . PHP_EOL;
    $maxLimit = max(1, $maxLimit);
    $defaultLimit = min(max(1, $defaultLimit), $maxLimit);

    if (count($arguments) > 1) {
        return ['valid' => false, 'limit' => $defaultLimit, 'message' => 'Invalid arguments.' . PHP_EOL . PHP_EOL . $usage];
    }

    if ($arguments === []) {
        return ['valid' => true, 'limit' => $defaultLimit, 'message' => ''];
    }

    $argument = $arguments[0];
    if (!is_string($argument) || !preg_match('/^--limit=([0-9]+)$/', $argument, $matches)) {
        return ['valid' => false, 'limit' => $defaultLimit, 'message' => 'Invalid arguments.' . PHP_EOL . PHP_EOL . $usage];
    }

    $limit = (int) $matches[1];
    if ($limit < 1 || $limit > $maxLimit) {
        return ['valid' => false, 'limit' => $defaultLimit, 'message' => 'Invalid limit.' . PHP_EOL . PHP_EOL . $usage];
    }

    return ['valid' => true, 'limit' => $limit, 'message' => ''];
}

$parsed = dispatchLimit(
    $arguments,
    (int) Config::get('app.event_dispatch_limit_default', 10),
    (int) Config::get('app.event_dispatch_limit_max', 100),
);

if (!$parsed['valid']) {
    fwrite(STDERR, $parsed['message']);
    exit(2);
}

try {
    $connection = (new Database(Config::get('database')))->connection();
    $events = new EventRepository($connection);
    $lock = new DispatcherLock($connection);

    if (!$lock->acquire()) {
        echo 'Dispatcher already running.' . PHP_EOL;
        exit(3);
    }

    $dispatcher = new EventDispatcher(
        $events,
        new SimulatedEventProcessor(),
    );

    try {
        $recovered = $events->recoverStaleProcessing((int) Config::get('app.event_processing_timeout_minutes', 15));
        $result = $dispatcher->dispatchBatch($parsed['limit']);
    } finally {
        $lock->release();
    }

    echo sprintf('Recovered stale events: %d%s', $recovered, PHP_EOL);
    echo sprintf('Processed: %d%s', $result['processed'], PHP_EOL);
    echo sprintf('Retried: %d%s', $result['retried'], PHP_EOL);
    echo sprintf('Failed: %d%s', $result['failed'], PHP_EOL);
    echo sprintf('Total: %d%s', $result['total'], PHP_EOL);

    exit(0);
} catch (Throwable) {
    fwrite(STDERR, 'Event dispatcher failed.' . PHP_EOL);
    exit(1);
}
