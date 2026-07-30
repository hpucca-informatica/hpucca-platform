<?php

declare(strict_types=1);

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$database = new Database(Config::get('database'));
$migrationsPath = dirname(__DIR__) . '/database/migrations';

try {
    $connection = $database->connection();
    $connection->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(255) PRIMARY KEY,
            executed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )'
    );

    $executed = $connection
        ->query('SELECT migration FROM schema_migrations')
        ->fetchAll(PDO::FETCH_COLUMN);

    foreach (glob($migrationsPath . '/*.sql') ?: [] as $migrationFile) {
        $migration = basename($migrationFile);

        if (in_array($migration, $executed, true)) {
            continue;
        }

        $sql = file_get_contents($migrationFile);

        if ($sql === false) {
            throw new RuntimeException('Could not read migration file.');
        }

        $connection->beginTransaction();

        try {
            $connection->exec($sql);

            $statement = $connection->prepare(
                'INSERT INTO schema_migrations (migration, executed_at) VALUES (:migration, NOW())'
            );
            $statement->execute([
                'migration' => $migration,
            ]);

            $connection->commit();
            echo sprintf("Migrated: %s%s", $migration, PHP_EOL);
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    echo 'Migrations completed.' . PHP_EOL;
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, 'Migration failed.' . PHP_EOL);
    exit(1);
}
