<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/validate.php';
require APP_ROOT . '/app/models.php';

[$method, $body] = api_start(['GET', 'POST', 'PUT', 'DELETE']);
$pdo = db();
$id = (string)($_GET['id'] ?? '');

if ($method === 'GET') {
    json_out(200, ['jobs' => all_jobs($pdo)]);
}

if ($method === 'POST') {
    [$clean, $errors] = validate_job($body, $pdo);
    if ($errors) {
        json_out(422, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }
    json_out(201, ['job' => job_from_row(create_job($pdo, $clean))]);
}

if (!job_exists($pdo, $id)) {
    json_out(404, ['error' => 'Job not found.']);
}

if ($method === 'PUT') {
    [$clean, $errors] = validate_job($body, $pdo);
    if ($errors) {
        json_out(422, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }
    update_job($pdo, $id, $clean);
    $st = $pdo->prepare('SELECT * FROM jobs WHERE job_id = ?');
    $st->execute([$id]);
    json_out(200, ['job' => job_from_row($st->fetch())]);
}

$pdo->prepare('DELETE FROM jobs WHERE job_id = ?')->execute([$id]);
json_out(200, ['deleted' => $id]);
