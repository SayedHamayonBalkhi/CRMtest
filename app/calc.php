<?php
// Calculations. These mirror public/js/calc.js exactly so the dashboard (JS) and reports/CSV (PHP) always agree.
// Stored on a job: quote, deposit, final_pay, hours, worker_cost, material_cost. Everything else is derived here.

function is_done(array $j): bool
{
    return $j['status'] === 'COMPLETED';
}

function job_metrics(array $j): array
{
    $totalPaid = $j['deposit'] + $j['final_pay'];
    $totalCost = $j['worker_cost'] + $j['material_cost'];
    $profit = $totalPaid - $totalCost;
    $perM = fn(float $n): float => $j['length_m'] > 0 ? $n / $j['length_m'] : 0.0;
    return [
        'total_paid' => $totalPaid,
        'total_cost' => $totalCost,
        'profit' => $profit,
        'margin' => $totalPaid > 0 ? $profit / $totalPaid : 0.0,
        'charged_pm' => $perM($totalPaid),
        'cost_pm' => $perM($totalCost),
        'profit_pm' => $perM($profit),
        'profit_ph' => $j['hours'] > 0 ? $profit / $j['hours'] : 0.0,
    ];
}

// Money figures only count completed jobs, because deposits on open jobs are not final revenue.
function job_totals(array $jobs): array
{
    $done = array_values(array_filter($jobs, 'is_done'));
    $revenue = 0.0;
    $profit = 0.0;
    $hours = 0.0;
    foreach ($done as $j) {
        $m = job_metrics($j);
        $revenue += $m['total_paid'];
        $profit += $m['profit'];
        $hours += $j['hours'];
    }
    return [
        'jobs' => count($done),
        'revenue' => $revenue,
        'profit' => $profit,
        'margin' => $revenue > 0 ? $profit / $revenue : 0.0,
        'profit_ph' => $hours > 0 ? $profit / $hours : 0.0,
        'avg_job' => count($done) ? $revenue / count($done) : 0.0,
    ];
}
