<?php
// Loads database/seed-data.json (49 jobs and 40 customers from the Earthbender sample sheet).
// Re-runnable: rows that already exist are skipped, unless $force is true (then they are reset to the sample values).

function seed_database(PDO $pdo, bool $force = false): array
{
    require_once __DIR__ . '/models.php';
    $data = json_decode(file_get_contents(APP_ROOT . '/database/seed-data.json'), true, 512, JSON_THROW_ON_ERROR);
    $added = ['customers' => 0, 'jobs' => 0];
    $updated = 0;

    $pdo->beginTransaction();
    foreach ($data['customers'] as $c) {
        $cols = [...CUSTOMER_COLS, 'since'];
        if (customer_exists($pdo, $c['id'])) {
            if ($force) {
                update_row($pdo, 'customers', 'id', $c['id'], $cols, array_intersect_key($c, array_flip($cols)));
                $updated++;
            }
            continue;
        }
        insert_row($pdo, 'customers', ['id', ...$cols], ['id' => $c['id'], ...array_intersect_key($c, array_flip($cols))]);
        $added['customers']++;
    }
    foreach ($data['jobs'] as $j) {
        $values = array_map('db_value', array_intersect_key($j, array_flip(JOB_COLS)));
        if (job_exists($pdo, $j['job_id'])) {
            if ($force) {
                update_row($pdo, 'jobs', 'job_id', $j['job_id'], JOB_COLS, $values);
                $updated++;
            }
            continue;
        }
        insert_row($pdo, 'jobs', ['job_id', ...JOB_COLS], ['job_id' => $j['job_id'], ...$values]);
        $added['jobs']++;
    }
    $pdo->commit();
    return $added + ['updated' => $updated];
}
