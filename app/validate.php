<?php
// Server-side validation. Each function returns [clean values, errors]. Errors are keyed by field name.

function v_text(array $in, string $key, int $max, bool $required, string $requiredMsg, array &$err): string
{
    $v = $in[$key] ?? '';
    if (!is_string($v)) {
        $err[$key] = 'Enter text.';
        return '';
    }
    $v = trim($v);
    if ($required && $v === '') {
        $err[$key] = $requiredMsg;
    } elseif (mb_strlen($v) > $max) {
        $err[$key] = "Use $max characters or fewer.";
    }
    return $v;
}

function v_enum(array $in, string $key, array $allowed, array &$err): string
{
    $v = $in[$key] ?? '';
    if (!is_string($v) || !in_array($v, $allowed, true)) {
        $err[$key] = 'Choose one of the listed options.';
        return $allowed[0];
    }
    return $v;
}

function v_date(array $in, string $key, array &$err): string
{
    $v = $in[$key] ?? '';
    if ($v === '' || $v === null) {
        return '';
    }
    $d = is_string($v) ? DateTime::createFromFormat('!Y-m-d', $v) : false;
    if (!$d || $d->format('Y-m-d') !== $v) {
        $err[$key] = 'Enter a valid date.';
        return '';
    }
    return $v;
}

// Blank means 0. Rejects non-numbers, negatives and absurd sizes.
function v_num(array $in, string $key, array &$err, bool $integer = false): float|int
{
    $v = $in[$key] ?? 0;
    if ($v === '' || $v === null) {
        return 0;
    }
    if (is_bool($v) || !is_numeric($v) || !is_finite((float)$v) || (float)$v < 0 || (float)$v > 10000000) {
        $err[$key] = 'Enter 0 or more.';
        return 0;
    }
    if ($integer && floor((float)$v) != (float)$v) {
        $err[$key] = 'Enter a whole number.';
        return 0;
    }
    return $integer ? (int)$v : round((float)$v, 2);
}

function validate_customer(array $in): array
{
    $err = [];
    $c = [
        'name' => v_text($in, 'name', 120, true, 'Customer name is required.', $err),
        'suburb' => v_text($in, 'suburb', 80, true, 'Suburb is required.', $err),
        'phone' => v_text($in, 'phone', 40, false, '', $err),
        'stage' => v_enum($in, 'stage', array_keys(CUSTOMER_STAGES), $err),
        'lead_source' => v_enum($in, 'lead_source', LEAD_SOURCES, $err),
        'email' => v_text($in, 'email', 190, false, '', $err),
        'address' => v_text($in, 'address', 190, false, '', $err),
        'follow_up' => v_date($in, 'follow_up', $err),
        'notes' => v_text($in, 'notes', 2000, false, '', $err),
    ];
    if ($c['phone'] !== '' && !isset($err['phone']) && !preg_match('/^[0-9+()\s-]{6,}$/', $c['phone'])) {
        $err['phone'] = 'Enter a valid phone number.';
    }
    if ($c['email'] !== '' && !isset($err['email']) && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Enter a valid email address.';
    }
    return [$c, $err];
}

function validate_job(array $in, PDO $pdo): array
{
    $err = [];
    $j = [
        'customer_id' => v_text($in, 'customer_id', 10, true, 'Choose a customer.', $err),
        'suburb' => v_text($in, 'suburb', 80, true, 'Suburb is required.', $err),
        'scope' => v_text($in, 'scope', 255, true, 'Describe the work.', $err),
        'type' => v_enum($in, 'type', JOB_TYPES, $err),
        'material' => v_enum($in, 'material', MATERIALS, $err),
        'sleepers' => v_enum($in, 'sleepers', SLEEPER_OPTIONS, $err),
        'length_m' => v_num($in, 'length_m', $err),
        'height_m' => v_num($in, 'height_m', $err),
        'quote' => v_num($in, 'quote', $err),
        'deposit' => v_num($in, 'deposit', $err),
        'final_pay' => v_num($in, 'final_pay', $err),
        'job_start' => v_date($in, 'job_start', $err),
        'job_end' => v_date($in, 'job_end', $err),
        'days_on_site' => v_num($in, 'days_on_site', $err, true),
        'workers' => filter_var($in['workers'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'hours' => v_num($in, 'hours', $err),
        'worker_cost' => v_num($in, 'worker_cost', $err),
        'material_cost' => v_num($in, 'material_cost', $err),
        'lead_source' => v_enum($in, 'lead_source', LEAD_SOURCES, $err),
        'status' => v_enum($in, 'status', array_keys(JOB_STATUSES), $err),
        'notes' => v_text($in, 'notes', 2000, false, '', $err),
    ];
    if (!isset($err['customer_id'])) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE id = ?');
        $st->execute([$j['customer_id']]);
        if (!(int)$st->fetchColumn()) {
            $err['customer_id'] = 'Choose a customer.';
        }
    }
    if (!isset($err['length_m']) && $j['length_m'] <= 0) {
        $err['length_m'] = 'Enter the length in metres.';
    }
    if ($j['job_start'] !== '' && $j['job_end'] !== '' && $j['job_end'] < $j['job_start']) {
        $err['job_end'] = 'End date is before the start date.';
    }
    return [$j, $err];
}
