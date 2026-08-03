<?php
/**
 * Folder-wise migration runner: executes every .sql file under
 * database/schema/ (numerically ordered) to build the schema, then
 * every .sql file under database/seeds/ to load lookup/demo data.
 *
 * Usage: php database/migrate.php [--seed]
 */

require __DIR__ . '/../includes/database.php';

function run_sql_directory(PDO $pdo, string $dir): void
{
    $files = glob($dir . '/*.sql');
    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        echo "Running " . basename($file) . " ... ";
        $sql = file_get_contents($file);

        try {
            $pdo->exec($sql);
            echo "OK\n";
        } catch (PDOException $e) {
            echo "FAILED\n";
            throw $e;
        }
    }
}

$pdo = db();

echo "== Applying schema ==\n";
run_sql_directory($pdo, __DIR__ . '/schema');

if (in_array('--seed', $argv, true)) {
    echo "== Applying seed data ==\n";
    run_sql_directory($pdo, __DIR__ . '/seeds');
}

echo "Done.\n";
