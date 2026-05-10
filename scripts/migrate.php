<?php
// scripts/migrate.php — run all migrations/*.sql files in lexical order.
// Tracks applied files in the _migrations table so re-running is a no-op.
// CLI only.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS _migrations (
        name TEXT PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

$applied = $pdo->query('SELECT name FROM _migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$dir = GUA_MIGRATIONS_PATH;
if (!is_dir($dir)) {
    fwrite(STDERR, "Migrations directory not found: $dir\n");
    exit(1);
}

$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "  - $name (already applied)\n";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Could not read $file\n");
        exit(1);
    }
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO _migrations (name) VALUES (:n)');
        $stmt->execute([':n' => $name]);
        $pdo->commit();
        echo "  + $name applied\n";
        $ran++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "FAILED on $name: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($ran === 0) {
    echo "Nothing to apply. DB is up to date.\n";
} else {
    echo "Applied $ran migration(s).\n";
}
echo "DB: " . GUA_DB_PATH . "\n";
