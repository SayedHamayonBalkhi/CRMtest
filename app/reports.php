<?php
// Report rows, grouping, summary and CSV. Mirrors the v8 reports logic; figures come from calc.php.

function margin_band(float $margin): string
{
    return $margin < 0.3 ? 'Margin under 30%' : ($margin < 0.5 ? 'Margin 30% to 50%' : 'Margin over 50%');
}

// amount = profit for job reports, total paid for the customer report. revenue = total paid.
function report_rows(string $cat, array $jobs, array $customers): array
{
    $names = [];
    foreach ($customers as $c) {
        $names[$c['id']] = $c['name'];
    }
    $done = array_values(array_filter($jobs, 'is_done'));

    if ($cat === 'CUSTOMERS') {
        $rows = [];
        foreach ($customers as $c) {
            $revenue = 0.0;
            $last = null;
            foreach ($done as $j) {
                if ($j['customer_id'] !== $c['id']) {
                    continue;
                }
                $revenue += job_metrics($j)['total_paid'];
                if ($j['job_end'] !== '' && ($last === null || $j['job_end'] > $last)) {
                    $last = $j['job_end'];
                }
            }
            $rows[] = ['id' => $c['id'], 'name' => $c['name'], 'group' => $c['suburb'], 'amount' => $revenue, 'revenue' => $revenue, 'date' => $last];
        }
        return $rows;
    }

    $rows = [];
    foreach ($done as $j) {
        $m = job_metrics($j);
        $group = match ($cat) {
            'JOBS' => margin_band($m['margin']),
            'SOURCES' => $j['lead_source'],
            'MATERIALS' => $j['material'],
            'TYPES' => $j['type'],
            'SUBURBS' => $j['suburb'],
        };
        $rows[] = [
            'id' => $j['job_id'],
            'name' => $j['job_id'] . ' · ' . ($names[$j['customer_id']] ?? 'Unknown customer'),
            'group' => $group,
            'amount' => $m['profit'],
            'revenue' => $m['total_paid'],
            'date' => $j['job_end'] !== '' ? $j['job_end'] : null,
        ];
    }
    return $rows;
}

function report_group_totals(array $rows): array
{
    $map = [];
    foreach ($rows as $r) {
        $map[$r['group']] ??= ['label' => $r['group'], 'count' => 0, 'amount' => 0.0];
        $map[$r['group']]['count']++;
        $map[$r['group']]['amount'] += $r['amount'];
    }
    $out = array_values($map);
    usort($out, fn($a, $b) => $b['amount'] <=> $a['amount']);
    return $out;
}

function report_build(string $cat, string $year, string $query, string $sort, array $jobs, array $customers): array
{
    $isCustomers = $cat === 'CUSTOMERS';
    $q = mb_strtolower(trim($query));

    $years = [];
    foreach ($jobs as $j) {
        if ($j['job_end'] !== '') {
            $years[substr($j['job_end'], 0, 4)] = true;
        }
    }
    $years = array_map('strval', array_keys($years));
    sort($years);

    $rows = array_values(array_filter(
        report_rows($cat, $jobs, $customers),
        function (array $r) use ($year, $q): bool {
            $inYear = $year === 'ALL' || ($r['date'] !== null && str_starts_with($r['date'], $year));
            $match = $q === '' || str_contains(mb_strtolower($r['name']), $q) || str_contains(mb_strtolower($r['group']), $q);
            return $inYear && $match;
        }
    ));
    usort($rows, function (array $a, array $b) use ($sort): int {
        if ($sort === 'amount') {
            return $b['amount'] <=> $a['amount'];
        }
        if ($sort === 'date') {
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        }
        return strcasecmp($a['name'], $b['name']);
    });

    $revenue = array_sum(array_column($rows, 'revenue'));
    $amount = array_sum(array_column($rows, 'amount'));
    $summary = $isCustomers
        ? [
            ['label' => 'Customers', 'value' => count($rows), 'format' => 'int'],
            ['label' => 'With completed jobs', 'value' => count(array_filter($rows, fn($r) => $r['revenue'] > 0)), 'format' => 'int'],
            ['label' => 'Total paid', 'value' => $revenue, 'format' => 'money'],
            ['label' => 'Average per customer', 'value' => count($rows) ? $revenue / count($rows) : 0, 'format' => 'money'],
        ]
        : [
            ['label' => 'Completed jobs', 'value' => count($rows), 'format' => 'int'],
            ['label' => 'Revenue', 'value' => $revenue, 'format' => 'money'],
            ['label' => 'Profit', 'value' => $amount, 'format' => 'money'],
            ['label' => 'Average margin', 'value' => $revenue > 0 ? $amount / $revenue : 0, 'format' => 'pct'],
        ];

    $round = fn(array $r): array => ['id' => $r['id'], 'name' => $r['name'], 'group' => $r['group'], 'amount' => round($r['amount'], 2), 'revenue' => round($r['revenue'], 2), 'date' => $r['date']];
    return [
        'category' => $cat,
        'label' => REPORT_CATEGORIES[$cat],
        'amount_label' => $isCustomers ? 'Total paid' : 'Profit',
        'is_customers' => $isCustomers,
        'years' => $years,
        'summary' => $summary,
        'groups' => array_map(fn($g) => ['label' => $g['label'], 'count' => $g['count'], 'amount' => round($g['amount'], 2)], report_group_totals($rows)),
        'rows' => array_map($round, $rows),
    ];
}

// Text cells starting with = + - @ are prefixed with ' so spreadsheet apps do not run them as formulas.
function csv_text(string $v): string
{
    if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) {
        $v = "'" . $v;
    }
    return '"' . str_replace('"', '""', $v) . '"';
}

function csv_num(float $v): string
{
    return '"' . rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '"';
}

function report_csv(array $report): string
{
    $out = "\xEF\xBB\xBF" . implode(',', ['Name', 'Group', $report['amount_label'], 'Revenue', 'Date']) . "\n";
    foreach ($report['rows'] as $r) {
        $out .= implode(',', [csv_text($r['name']), csv_text($r['group']), csv_num($r['amount']), csv_num($r['revenue']), csv_text($r['date'] ?? '')]) . "\n";
    }
    return $out;
}
