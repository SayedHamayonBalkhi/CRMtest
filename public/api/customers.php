<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/validate.php';
require APP_ROOT . '/app/models.php';

[$method, $body] = api_start(['GET', 'POST', 'PUT', 'DELETE']);
$pdo = db();
$id = (string)($_GET['id'] ?? '');

if ($method === 'GET') {
    json_out(200, ['customers' => all_customers($pdo)]);
}

if ($method === 'POST') {
    [$clean, $errors] = validate_customer($body);
    if ($errors) {
        json_out(422, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }
    json_out(201, ['customer' => create_customer($pdo, $clean)]);
}

if (!customer_exists($pdo, $id)) {
    json_out(404, ['error' => 'Customer not found.']);
}

if ($method === 'PUT') {
    [$clean, $errors] = validate_customer($body);
    if ($errors) {
        json_out(422, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }
    update_customer($pdo, $id, $clean);
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$id]);
    json_out(200, ['customer' => $st->fetch()]);
}

json_out(200, ['deleted' => $id, 'jobs_deleted' => delete_customer($pdo, $id)]);
