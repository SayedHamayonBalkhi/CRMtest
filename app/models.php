<?php
// Database access for customers and jobs. All SQL uses prepared statements; column names come from fixed lists below.

const CUSTOMER_COLS = ['name', 'phone', 'suburb', 'lead_source', 'stage', 'email', 'address', 'follow_up', 'notes'];
const JOB_COLS = ['customer_id', 'suburb', 'scope', 'type', 'material', 'sleepers', 'length_m', 'height_m', 'quote', 'deposit', 'final_pay',
    'job_start', 'job_end', 'days_on_site', 'workers', 'hours', 'worker_cost', 'material_cost', 'lead_source', 'status', 'notes'];

function job_from_row(array $r): array
{
    foreach (['length_m', 'height_m', 'quote', 'deposit', 'final_pay', 'hours', 'worker_cost', 'material_cost'] as $k) {
        $r[$k] = (float)$r[$k];
    }
    $r['days_on_site'] = (int)$r['days_on_site'];
    $r['workers'] = (bool)$r['workers'];
    return $r;
}

function all_customers(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM customers ORDER BY id')->fetchAll();
}

function all_jobs(PDO $pdo): array
{
    return array_map('job_from_row', $pdo->query('SELECT * FROM jobs ORDER BY job_id')->fetchAll());
}

// Next code such as C041 or J050.
function next_code(PDO $pdo, string $table, string $column, string $prefix): string
{
    $max = 0;
    foreach ($pdo->query("SELECT $column FROM $table")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $max = max($max, (int)preg_replace('/\D/', '', $id));
    }
    return $prefix . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

function customer_exists(PDO $pdo, string $id): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE id = ?');
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
}

function job_exists(PDO $pdo, string $id): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE job_id = ?');
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
}

function insert_row(PDO $pdo, string $table, array $cols, array $values): void
{
    $names = implode(', ', $cols);
    $marks = implode(', ', array_fill(0, count($cols), '?'));
    $pdo->prepare("INSERT INTO $table ($names) VALUES ($marks)")->execute(array_map(fn($c) => $values[$c], $cols)); // by column name, so key order never matters
}

function update_row(PDO $pdo, string $table, string $idCol, string $id, array $cols, array $values): void
{
    $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
    $pdo->prepare("UPDATE $table SET $set WHERE $idCol = ?")->execute([...array_map(fn($c) => $values[$c], $cols), $id]);
}

function db_value(mixed $v): mixed
{
    return is_bool($v) ? (int)$v : $v;
}

function create_customer(PDO $pdo, array $c): array
{
    $pdo->beginTransaction();
    $id = next_code($pdo, 'customers', 'id', 'C');
    $row = ['id' => $id, ...$c, 'since' => date('Y-m-d')];
    insert_row($pdo, 'customers', array_keys($row), $row);
    $pdo->commit();
    return $row;
}

function update_customer(PDO $pdo, string $id, array $c): void
{
    update_row($pdo, 'customers', 'id', $id, CUSTOMER_COLS, $c);
}

// Deleting a customer also deletes their jobs. Returns how many jobs went with them.
function delete_customer(PDO $pdo, string $id): int
{
    $pdo->beginTransaction();
    $st = $pdo->prepare('DELETE FROM jobs WHERE customer_id = ?');
    $st->execute([$id]);
    $jobs = $st->rowCount();
    $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    $pdo->commit();
    return $jobs;
}

function create_job(PDO $pdo, array $j): array
{
    $pdo->beginTransaction();
    $id = next_code($pdo, 'jobs', 'job_id', 'J');
    $row = ['job_id' => $id, ...array_map('db_value', $j)];
    insert_row($pdo, 'jobs', array_keys($row), $row);
    $pdo->commit();
    return $row;
}

function update_job(PDO $pdo, string $id, array $j): void
{
    update_row($pdo, 'jobs', 'job_id', $id, JOB_COLS, array_map('db_value', $j));
}
