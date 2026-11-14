// Calculations. Mirrors app/calc.php so the dashboard (JS) and the reports/CSV (PHP) always agree.
// Stored on a job: quote, deposit, final_pay, hours, worker_cost, material_cost. Everything else is derived here.

export const isDone = (j) => j.status === 'COMPLETED';

export function jobMetrics(j) {
  const totalPaid = j.deposit + j.final_pay;
  const totalCost = j.worker_cost + j.material_cost;
  const profit = totalPaid - totalCost;
  const perM = (n) => (j.length_m > 0 ? n / j.length_m : 0);
  return {
    totalPaid,
    totalCost,
    profit,
    margin: totalPaid > 0 ? profit / totalPaid : 0,
    chargedPm: perM(totalPaid),
    costPm: perM(totalCost),
    profitPm: perM(profit),
    profitPh: j.hours > 0 ? profit / j.hours : 0,
  };
}

const sum = (xs) => xs.reduce((a, b) => a + b, 0);

// Money figures only count completed jobs, because deposits on open jobs are not final revenue.
export function totals(jobs) {
  const done = jobs.filter(isDone);
  const revenue = sum(done.map((j) => jobMetrics(j).totalPaid));
  const profit = sum(done.map((j) => jobMetrics(j).profit));
  const hours = sum(done.map((j) => j.hours));
  return {
    jobs: done.length,
    revenue,
    profit,
    margin: revenue > 0 ? profit / revenue : 0,
    profitPh: hours > 0 ? profit / hours : 0,
    avgJob: done.length ? revenue / done.length : 0,
  };
}

export function groupJobs(jobs, keyFn) {
  const map = new Map();
  jobs.filter(isDone).forEach((j) => map.set(keyFn(j), [...(map.get(keyFn(j)) ?? []), j]));
  return [...map.entries()]
    .map(([label, js]) => {
      const t = totals(js);
      return { label, count: js.length, revenue: t.revenue, profit: t.profit, margin: t.margin };
    })
    .sort((a, b) => b.revenue - a.revenue);
}

export function customerJobs(c, jobs) {
  return jobs.filter((j) => j.customer_id === c.id).sort((a, b) => b.job_start.localeCompare(a.job_start));
}

export function isRepeat(c, jobs) {
  return c.lead_source === 'Repeat Customer' || customerJobs(c, jobs).length >= 2;
}
