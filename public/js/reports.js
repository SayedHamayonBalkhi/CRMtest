import './shell.js';
import { api, BOOT } from './api.js';
import { fmtDate, money0, pct } from './format.js';
import { barRow, el, emptyState, errorState, metricCard, toast } from './ui.js';

const view = document.getElementById('view');
const category = document.getElementById('f-category');
const year = document.getElementById('f-year');
const query = document.getElementById('f-query');
const sort = document.getElementById('f-sort');
const exportLink = document.getElementById('export-csv');
let requestId = 0;
let timer;

BOOT.reportCategories.forEach((c) => category.append(el('option', { value: c.id }, c.label)));

const params = () => ({ category: category.value, year: year.value, q: query.value.trim(), sort: sort.value });
const FORMATS = { int: String, money: money0, pct };

function setYears(years) {
  const keep = year.value;
  year.replaceChildren(el('option', { value: 'ALL' }, 'All years'), ...years.map((y) => el('option', { value: y }, `Finished in ${y}`)));
  year.value = years.includes(keep) ? keep : 'ALL';
}

function render(r) {
  const { is_customers: isCustomers, amount_label: amountLabel } = r;
  document.getElementById('subtitle').textContent = `${r.label} · based on completed jobs.`;
  document.getElementById('summary').replaceChildren(...r.summary.map((s) => metricCard({ label: s.label, value: FORMATS[s.format](s.value) })));
  const empty = r.rows.length === 0;
  exportLink.setAttribute('aria-disabled', String(empty));
  exportLink.href = empty ? '#' : api.reportCsvUrl(params());
  if (empty) return view.replaceChildren(emptyState('No data for these filters', 'Try another year or clear the search.'));

  const max = Math.max(1, ...r.groups.map((g) => Math.abs(g.amount)));
  const heads = ['Name', isCustomers ? 'Suburb' : 'Group', amountLabel, isCustomers ? 'Last job' : 'Finished'];
  view.replaceChildren(el('div', { class: 'grid reports' },
    el('section', { class: 'card' }, el('h2', {}, isCustomers ? 'Total paid by suburb' : 'Profit by group'),
      el('ul', { class: 'bars' }, r.groups.map((g) => barRow(g.label, `${money0(g.amount)} · ${g.count} ${isCustomers ? (g.count === 1 ? 'customer' : 'customers') : (g.count === 1 ? 'job' : 'jobs')}`, (g.amount / max) * 100)))),
    el('section', { class: 'table-wrap flush' }, el('table', { class: 'table-mid' },
      el('caption', { class: 'sr-only' }, `${r.label} rows`),
      el('thead', {}, el('tr', {}, heads.map((h) => el('th', { scope: 'col' }, h)))),
      el('tbody', {}, r.rows.map((x) => el('tr', {}, el('td', { class: 'strong' }, x.name), el('td', {}, x.group), el('td', { class: 'nowrap' }, money0(x.amount)), el('td', { class: 'nowrap' }, fmtDate(x.date)))))))));
}

async function load() {
  const id = ++requestId;
  try {
    const data = await api.report(params());
    if (id !== requestId) return; // a newer request is already running
    setYears(data.years);
    render(data);
  } catch (err) {
    if (id === requestId) {
      toast('error', err.message);
      view.replaceChildren(errorState(err));
    }
  }
}

[category, year, sort].forEach((i) => i.addEventListener('change', load));
query.addEventListener('input', () => {
  clearTimeout(timer);
  timer = setTimeout(load, 250);
});
exportLink.addEventListener('click', (e) => exportLink.getAttribute('aria-disabled') === 'true' && e.preventDefault());
load();
