<?php
// Command line: php tests/run.php
// Builds a throw-away SQLite database, loads the sample data and checks the figures against the source sheet.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$tmp = sys_get_temp_dir() . '/eb-test-' . getmypid() . '.sqlite';
putenv('EB_SQLITE_PATH=' . $tmp);
putenv('EB_ALLOW_HTTP=1');
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/models.php';
require APP_ROOT . '/app/seed.php';
require APP_ROOT . '/app/reports.php';
require APP_ROOT . '/app/validate.php';

$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $fail;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  $detail") . "\n";
    $fail += $ok ? 0 : 1;
}
$near = fn($a, $b, $tol = 0.5) => abs($a - $b) <= $tol;

$pdo = db();
db_init($pdo);
$r1 = seed_database($pdo);
$r2 = seed_database($pdo); // re-run must add nothing
check('seed adds 40 customers and 49 jobs', $r1['customers'] === 40 && $r1['jobs'] === 49, json_encode($r1));
check('seed is re-runnable (second run adds nothing)', $r2['customers'] === 0 && $r2['jobs'] === 0, json_encode($r2));

$jobs = all_jobs($pdo);
$customers = all_customers($pdo);
$t = job_totals($jobs);
check('49 jobs', count($jobs) === 49);
check('40 customers', count($customers) === 40);
check('revenue $172,209', $near($t['revenue'], 172209), (string)$t['revenue']);
check('profit $60,220', $near($t['profit'], 60220), (string)$t['profit']);
check('overall margin about 35%', $near($t['margin'], 0.35, 0.005), (string)$t['margin']);
check('profit per hour about $133', $near($t['profit_ph'], 133, 0.5), (string)$t['profit_ph']);

$byCust = [];
foreach ($jobs as $j) { $byCust[$j['customer_id']] = ($byCust[$j['customer_id']] ?? 0) + 1; }
$repeat = count(array_filter($customers, fn($c) => $c['lead_source'] === 'Repeat Customer' || ($byCust[$c['id']] ?? 0) >= 2));
check('10 repeat customers', $repeat === 10, (string)$repeat);

$flagged = array_column(array_filter($jobs, fn($j) => $j['notes'] !== ''), 'job_id');
sort($flagged);
check('Check data flags J009 J019 J022 J031 J046 J047', $flagged === ['J009', 'J019', 'J022', 'J031', 'J046', 'J047'], implode(',', $flagged));

foreach (array_keys(REPORT_CATEGORIES) as $cat) {
    $rep = report_build($cat, 'ALL', '', 'amount', $jobs, $customers);
    $sum = array_sum(array_column($rep['rows'], 'amount'));
    $expect = $cat === 'CUSTOMERS' ? $t['revenue'] : $t['profit'];
    check("report $cat total matches dashboard", $near($sum, $expect, 1), (string)$sum);
}
$csv = report_csv(report_build('JOBS', 'ALL', '', 'amount', $jobs, $customers));
check('CSV has header + 49 rows', count(explode("\n", trim($csv))) === 50);
check('CSV neutralises formulas', str_contains(report_csv(['amount_label' => 'Profit', 'rows' => [['name' => '=1+1', 'group' => 'x', 'amount' => -5.5, 'revenue' => 1, 'date' => null]]]), "\"'=1+1\""));
check('year filter works', report_build('JOBS', '2024', '', 'amount', $jobs, $customers)['summary'][0]['value'] < 49);
check('search with SQL-looking text is harmless', report_build('JOBS', 'ALL', "' OR 1=1 --", 'amount', $jobs, $customers)['rows'] === []);

// Validation
[, $e] = validate_customer(['name' => '', 'suburb' => '', 'stage' => 'NOPE', 'lead_source' => 'Google', 'phone' => 'abc', 'email' => 'x']);
check('customer validation rejects bad input', isset($e['name'], $e['suburb'], $e['stage'], $e['phone'], $e['email']), json_encode($e));
[, $e] = validate_job(['customer_id' => 'C001', 'suburb' => 'A', 'scope' => 'x', 'type' => 'Boundary', 'material' => 'Timber', 'sleepers' => 'No Sleepers', 'lead_source' => 'Google', 'status' => 'QUOTED', 'length_m' => 0, 'quote' => -1, 'job_start' => '2024-02-01', 'job_end' => '2024-01-01'], $pdo);
check('job validation rejects length 0, negative quote, end before start', isset($e['length_m'], $e['quote'], $e['job_end']), json_encode($e));

// Cascade delete and code generation
$id = create_customer($pdo, ['name' => 'Test', 'phone' => '', 'suburb' => 'X', 'lead_source' => 'Google', 'stage' => 'ENQUIRY', 'email' => '', 'address' => '', 'follow_up' => '', 'notes' => ''])['id'];
check('new customer gets next code C041', $id === 'C041', $id);
[$j] = validate_job(['customer_id' => $id, 'suburb' => 'X', 'scope' => 'Fence', 'type' => 'Boundary', 'material' => 'Timber', 'sleepers' => 'No Sleepers', 'lead_source' => 'Google', 'status' => 'QUOTED', 'length_m' => 5], $pdo);
$jid = create_job($pdo, $j)['job_id'];
check('new job gets next code J050', $jid === 'J050', $jid);
update_customer($pdo, $id, ['name' => 'Renamed', 'suburb' => 'Narre Warren', 'phone' => '0400 111 222', 'stage' => 'QUOTED', 'lead_source' => 'Referral', 'email' => 'a@b.co', 'address' => '1 Test St', 'follow_up' => '2026-12-01', 'notes' => 'n']);
$u = $pdo->query("SELECT * FROM customers WHERE id = '$id'")->fetch();
check('updating a customer keeps every field in its own column', $u['name'] === 'Renamed' && $u['suburb'] === 'Narre Warren' && $u['phone'] === '0400 111 222' && $u['stage'] === 'QUOTED' && $u['lead_source'] === 'Referral' && $u['follow_up'] === '2026-12-01');
check('deleting a customer deletes their jobs', delete_customer($pdo, $id) === 1 && !job_exists($pdo, $jid));

@unlink($tmp);
echo $fail ? "\n$fail check(s) FAILED\n" : "\nAll checks passed\n";
exit($fail ? 1 : 0);
