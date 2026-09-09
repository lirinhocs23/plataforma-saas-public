<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$applied = $pdo->query('SELECT version,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);

foreach (glob(ROOT_PATH . '/migrations/*.sql') ?: [] as $file) {
    $version = basename($file);
    $sql = file_get_contents($file);
    $checksum = hash('sha256', $sql ?: '');
    if (isset($applied[$version])) {
        if (!hash_equals($applied[$version], $checksum)) throw new RuntimeException("Migration aplicada foi alterada: {$version}");
        echo "OK {$version}\n"; continue;
    }
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version,checksum) VALUES (:version,:checksum)');
        $stmt->execute(compact('version','checksum'));
        echo "APLICADA {$version}\n";
    } catch (Throwable $e) {
        throw $e;
    }
}
