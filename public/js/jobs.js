import './shell.js';
import { api, BOOT, url } from './api.js';
import { isDone, jobMetrics, totals } from './calc.js';
import { fmtDate, money0, pct } from './format.js';
import { openJobForm } from './job-form.js';
import { badge, confirmDialog, el, emptyState, errorState, icon, metricCard, toast } from './ui.js';

const view = document.getElementById('view');
const ids = { query: 'f-query', status: 'f-status', type: 'f-type', material: 'f-material', source: 'f-source', sort: 'f-sort' };
const inputs = Object.fromEntries(Object.entries(ids).map(([k, id]) => [k, document.getElementById(id)]));
const clearBtn = document.getElementById('clear-filters');
const DEFAULTS = { query: '', status: 'ALL', type: 'ALL', material: 'ALL', source: 'ALL', sort: 'newest' };
const STATUS_TONE = { QUOTED: 'neutral', BOOKED: 'info', IN_PROGRESS: 'warning', COMPLETED: 'success' };
let customers = [];
let jobs = [];

const nameOf = (id) => customers.find((c) => c.id === id)?.name ?? 'Unknown customer';
const statusLabel = (id) => BOOT.jobStatuses.find((s) => s.id === id)?.label ?? id;

function filtered() {
  const text = inputs.query.value.trim().toLowerCase();
  const { status, type, material, source, sort } = Object.fromEntries(Object.entries(inputs).map(([k, i]) => [k, i.value]));
  return jobs
    .filter((j) =>
      (!text || [j.job_id, nameOf(j.customer_id), j.scope, j.suburb].join(' ').toLowerCase().includes(text)) &&
      (status === 'ALL' || j.status === status) && (type === 'ALL' || j.type === type) &&
      (material === 'ALL' || j.material === material) && (source === 'ALL' || j.lead_source === source))
    .sort((a, b) => {
      const ma = jobMetrics(a);
      const mb = jobMetrics(b);
      if (sort === 'profit') return mb.profit - ma.profit;
      if (sort === 'margin') return mb.margin - ma.margin;
      if (sort === 'value') return mb.totalPaid - ma.totalPaid;
      return (b.job_end || b.job_start).localeCompare(a.job_end || a.job_start);
    });
}

function row(j) {
  const m = jobMetrics(j);
  const done = isDone(j);
  return el('tr', {},
    el('td', { class: 'nowrap strong' }, j.job_id),
    el('td', {}, nameOf(j.customer_id), el('span', { class: 'sub' }, j.suburb)),
    el('td', {}, el('span', { class: 'scope', title: j.scope }, j.scope), el('span', { class: 'sub' }, j.type),
      j.notes && el('span', {}, badge('warning', 'Check data', { title: j.notes, tabindex: '0', 'aria-label': `Check data: ${j.notes}` }))),
    el('td', {}, j.material),
    el('td', { class: 'nowrap' }, `${j.length_m} m`),
    el('td', { class: 'nowrap' }, money0(j.quote)),
    el('td', { class: 'nowrap' }, done ? money0(m.totalPaid) : '—'),
    el('td', { class: 'nowrap' }, done ? money0(m.profit) : '—'),
    el('td', { class: 'nowrap' }, done ? pct(m.margin) : '—'),
    el('td', { class: 'nowrap' }, fmtDate(j.job_end)),
    el('td', {}, badge(STATUS_TONE[j.status], statusLabel(j.status))),
    el('td', { class: 'actions' },
      el('button', { type: 'button', class: 'icon-btn', 'aria-label': `Edit job ${j.job_id}`, onclick: () => openForm(j) }, icon('pencil', 16)),
      el('button', { type: 'button', class: 'icon-btn', 'aria-label': `Delete job ${j.job_id}`, onclick: () => removeJob(j) }, icon('trash', 16))));
}

function render() {
  const rows = filtered();
  const t = totals(rows);
  document.getElementById('subtitle').textContent = `${jobs.length} jobs in total. Profit figures count completed jobs only.`;
  document.getElementById('summary').replaceChildren(
    metricCard({ label: 'Jobs shown', value: String(rows.length) }), metricCard({ label: 'Revenue', value: money0(t.revenue) }),
    metricCard({ label: 'Profit', value: money0(t.profit) }), metricCard({ label: 'Average margin', value: pct(t.margin) }));
  clearBtn.hidden = Object.entries(inputs).every(([k, i]) => i.value === DEFAULTS[k]);

  if (rows.length === 0) return view.replaceChildren(emptyState('No jobs match these filters', 'Try a different search or clear the filters.'));
  const heads = ['Job', 'Customer', 'Scope', 'Material', 'Length', 'Quote', 'Paid', 'Profit', 'Margin', 'Finished', 'Status', ''];
  view.replaceChildren(el('div', { class: 'table-wrap' }, el('table', { class: 'table-wide' },
    el('caption', { class: 'sr-only' }, 'Jobs'),
    el('thead', {}, el('tr', {}, heads.map((h) => el('th', { scope: 'col' }, h || el('span', { class: 'sr-only' }, 'Actions'))))),
    el('tbody', {}, rows.map(row)))));
}

function openForm(job = null) {
  openJobForm({
    job,
    customers,
    onSaved: (saved) => {
      jobs = job ? jobs.map((x) => (x.job_id === saved.job_id ? saved : x)) : [...jobs, saved];
      render();
    },
  });
}

async function removeJob(j) {
  const ok = await confirmDialog({ title: 'Delete job', message: `Delete job ${j.job_id} (${j.scope})? This cannot be undone.`, confirmLabel: 'Delete job' });
  if (!ok) return;
  try {
    await api.remove('jobs', j.job_id);
    jobs = jobs.filter((x) => x.job_id !== j.job_id);
    toast('success', `Job ${j.job_id} deleted`);
    render();
  } catch (err) {
    toast('error', err.message);
  }
}

document.getElementById('add-job').addEventListener('click', () => openForm());
clearBtn.addEventListener('click', () => {
  Object.entries(inputs).forEach(([k, i]) => (i.value = DEFAULTS[k]));
  render();
});
Object.entries(inputs).forEach(([k, i]) => i.addEventListener(k === 'query' ? 'input' : 'change', render));

(async () => {
  try {
    [{ customers }, { jobs }] = await Promise.all([api.list('customers'), api.list('jobs')]);
    render();
  } catch (err) {
    return view.replaceChildren(errorState(err));
  }
  // Links from the Dashboard and Customers open the new-job form or a job's edit form directly.
  const params = new URLSearchParams(window.location.search);
  const editId = params.get('edit');
  const target = editId && jobs.find((j) => j.job_id === editId);
  if (params.get('new') === '1' || target) {
    window.history.replaceState(null, '', url('jobs'));
    openForm(target || null);
  }
})();
