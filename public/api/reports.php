<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/models.php';
require APP_ROOT . '/app/reports.php';

api_start(['GET']);
$pdo = db();

$cat = (string)($_GET['category'] ?? 'JOBS');
if (!isset(REPORT_CATEGORIES[$cat])) {
    json_out(400, ['error' => 'Unknown report category.']);
}
$year = (string)($_GET['year'] ?? 'ALL');
if ($year !== 'ALL' && !preg_match('/^\d{4}$/', $year)) {
    json_out(400, ['error' => 'Year must be four digits or ALL.']);
}
$sort = in_array($_GET['sort'] ?? '', ['name', 'amount', 'date'], true) ? $_GET['sort'] : 'amount';
$query = substr((string)($_GET['q'] ?? ''), 0, 100);

$report = report_build($cat, $year, $query, $sort, all_jobs($pdo), all_customers($pdo));

if (($_GET['format'] ?? '') === 'csv') {
    $file = 'earthbender-' . strtolower($cat) . ($year === 'ALL' ? '' : '-' . $year) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo report_csv($report);
    exit;
}
json_out(200, $report);
