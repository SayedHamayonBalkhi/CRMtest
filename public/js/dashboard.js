import './shell.js';
import { api, url } from './api.js';
import { groupJobs, isDone, isRepeat, jobMetrics, totals } from './calc.js';
import { fmtDate, money0, pct } from './format.js';
import { badge, barRow, el, emptyState, errorState, metricCard } from './ui.js';

const view = document.getElementById('view');

function sourcesCard(jobs) {
  const sources = groupJobs(jobs, (j) => j.lead_source).sort((a, b) => b.count - a.count);
  const max = Math.max(1, ...sources.map((s) => s.count));
  return el('section', { class: 'card' }, el('h2', {}, 'Where jobs come from'),
    sources.length === 0
      ? el('p', { class: 'note' }, 'No completed jobs yet.')
      : el('ul', { class: 'bars' }, sources.map((s) => barRow(s.label, `${s.count} ${s.count === 1 ? 'job' : 'jobs'} · ${money0(s.profit)} profit`, (s.count / max) * 100))));
}

function materialsCard(jobs) {
  const materials = groupJobs(jobs, (j) => j.material);
  return el('section', { class: 'card' }, el('h2', {}, 'Profit margin by material'),
    materials.length === 0
      ? el('p', { class: 'note' }, 'No completed jobs yet.')
      : el('ul', { class: 'bars' }, materials.map((m) => barRow(m.label, `${pct(m.margin)} · ${m.count} ${m.count === 1 ? 'job' : 'jobs'}`, m.margin * 100))));
}

function recentCard(jobs, nameOf) {
  const recent = [...jobs].sort((a, b) => (b.job_end || b.job_start).localeCompare(a.job_end || a.job_start)).slice(0, 6);
  return el('section', { class: 'card' },
    el('div', { class: 'card-head' }, el('h2', {}, 'Recent jobs'), el('a', { class: 'link', href: url('jobs') }, 'View all')),
    recent.length === 0
      ? el('p', { class: 'note' }, 'No jobs yet.')
      : el('ul', { class: 'rows' }, recent.map((j) => el('li', {},
          el('div', {}, el('a', { class: 'title', href: url(`jobs?edit=${encodeURIComponent(j.job_id)}`) }, `${j.job_id} · ${nameOf(j.customer_id)}`), el('p', { class: 'sub' }, `${j.scope} · ${j.suburb}`)),
          el('div', { class: 'right' }, el('strong', {}, isDone(j) ? money0(jobMetrics(j).profit) : money0(j.quote)), el('span', { class: 'sub' }, `${isDone(j) ? 'profit' : 'quoted'} · ${fmtDate(j.job_end)}`))))));
}

function followUpsCard(customers) {
  const due = customers.filter((c) => c.follow_up).sort((a, b) => a.follow_up.localeCompare(b.follow_up)).slice(0, 6);
  return el('section', { class: 'card' },
    el('div', { class: 'card-head' }, el('h2', {}, 'Customer follow-ups'), el('a', { class: 'link', href: url('customers') }, 'Customers')),
    due.length === 0
      ? emptyState('No follow-ups scheduled', 'Add a follow-up date to a customer and it will show here.')
      : el('ul', { class: 'rows' }, due.map((c) => el('li', {},
          el('div', {}, el('p', { class: 'title' }, c.name), el('p', { class: 'sub' }, `${c.suburb}${c.phone ? ` · ${c.phone}` : ''}`)),
          badge('warning', fmtDate(c.follow_up))))));
}

async function main() {
  try {
    const [{ customers }, { jobs }] = await Promise.all([api.list('customers'), api.list('jobs')]);
    const t = totals(jobs);
    const repeat = customers.filter((c) => isRepeat(c, jobs)).length;
    const nameOf = (id) => customers.find((c) => c.id === id)?.name ?? 'Unknown customer';
    const dates = jobs.filter(isDone).map((j) => j.job_end).filter(Boolean).sort();
    document.getElementById('period').textContent = dates.length ? `Completed jobs · ${fmtDate(dates[0])} to ${fmtDate(dates.at(-1))}` : 'No completed jobs yet';

    view.replaceChildren(
      el('div', { class: 'grid cols-5' },
        metricCard({ label: 'Revenue', value: money0(t.revenue), hint: `${t.jobs} completed jobs`, href: url('reports') }),
        metricCard({ label: 'Profit', value: money0(t.profit), hint: `${pct(t.margin)} average margin`, href: url('reports') }),
        metricCard({ label: 'Profit per hour', value: money0(t.profitPh), hint: 'Across all hours worked', href: url('jobs') }),
        metricCard({ label: 'Average job value', value: money0(t.avgJob), hint: 'Money received per job', href: url('jobs') }),
        metricCard({ label: 'Customers', value: String(customers.length), hint: `${repeat} repeat customers`, href: url('customers') })),
      el('div', { class: 'grid cols-2' }, sourcesCard(jobs), materialsCard(jobs), recentCard(jobs, nameOf), followUpsCard(customers)),
    );
  } catch (err) {
    view.replaceChildren(errorState(err));
  }
}
main();
