<?php
// core/scripts/migrate.php — run all *.sql files in core/migrations/ AND
// site/migrations/, in lexical order within each source. Tracks applied
// files in the _migrations table (with a 'source' column) so re-running
// is a no-op. CLI only.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../lib/bootstrap.php';

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS _migrations (
        source TEXT NOT NULL,
        name TEXT NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (source, name)
    )"
);

$sources = [
    'core' => GUA_CORE_PATH . '/migrations',
    'site' => GUA_SITE_PATH . '/migrations',
];

$applied = [];
foreach ($pdo->query('SELECT source, name FROM _migrations') as $row) {
    $applied[$row['source']][$row['name']] = true;
}

$ran = 0;
$skipped = 0;

foreach ($sources as $source => $dir) {
    if (!is_dir($dir)) {
        echo "  ($source: no migrations directory at $dir, skipping)\n";
        continue;
    }
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$source][$name])) {
            echo "  - [$source] $name (already applied)\n";
            $skipped++;
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
            $stmt = $pdo->prepare('INSERT INTO _migrations (source, name) VALUES (:s, :n)');
            $stmt->execute([':s' => $source, ':n' => $name]);
            $pdo->commit();
            echo "  + [$source] $name applied\n";
            $ran++;
        } catch (Throwable $e) {
            $pdo->rollBack();
            fwrite(STDERR, "FAILED on [$source] $name: " . $e->getMessage() . "\n");
            exit(1);
        }
    }
}

if ($ran === 0) {
    echo "Nothing to apply. DB is up to date ($skipped already applied).\n";
} else {
    echo "Applied $ran migration(s). $skipped already applied.\n";
}
echo "DB:   " . GUA_DB_PATH . "\n";
echo "Core: " . GUA_CORE_VERSION . "\n";
