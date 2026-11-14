<?php
// Command line: php database/seed.php [--force]
// Creates the tables if needed and loads the Earthbender sample data. Existing rows are kept unless --force is given.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/seed.php';

$pdo = db();
db_init($pdo);
$r = seed_database($pdo, in_array('--force', $argv, true));
echo "Customers added: {$r['customers']}, jobs added: {$r['jobs']}, existing rows reset: {$r['updated']}\n";
