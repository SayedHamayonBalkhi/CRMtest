import './shell.js';
import { api, BOOT, url } from './api.js';
import { customerJobs, isDone, isRepeat, jobMetrics, totals } from './calc.js';
import { fmtDate, money0, pct } from './format.js';
import { openCustomerForm } from './customer-form.js';
import { openJobForm } from './job-form.js';
import { avatar, badge, confirmDialog, el, emptyState, errorState, icon, openModal, toast } from './ui.js';

const view = document.getElementById('view');
const q = document.getElementById('f-query');
const stageFilter = document.getElementById('f-stage');
const sourceFilter = document.getElementById('f-source');
let customers = [];
let jobs = [];

const stageLabel = (id) => BOOT.stages.find((s) => s.id === id)?.label ?? id;

function matches(c) {
  const text = q.value.trim().toLowerCase();
  if (text) {
    const scopes = customerJobs(c, jobs).map((j) => `${j.job_id} ${j.scope}`);
    if (![c.name, c.suburb, c.phone, c.email, c.address, ...scopes].join(' ').toLowerCase().includes(text)) return false;
  }
  return (stageFilter.value === 'ALL' || c.stage === stageFilter.value) && (sourceFilter.value === 'ALL' || c.lead_source === sourceFilter.value);
}

// ---- Board ----

async function moveCustomer(id, stage) {
  const c = customers.find((x) => x.id === id);
  if (!c || c.stage === stage) return;
  try {
    const { customer } = await api.update('customers', id, { ...c, stage });
    customers = customers.map((x) => (x.id === id ? customer : x));
    toast('success', `${c.name} moved to ${stageLabel(stage)}`);
  } catch (err) {
    toast('error', err.message);
  }
  render();
}

function card(c) {
  const cj = customerJobs(c, jobs);
  const t = totals(cj);
  const li = el('li', { class: 'ccard', draggable: 'true' },
    el('button', { type: 'button', class: 'ccard-main', onclick: () => openDetail(c.id) },
      el('div', { class: 'ccard-top' }, avatar(c.name, 'sm'), el('div', {}, el('p', { class: 'ccard-name' }, c.name), el('p', { class: 'ccard-suburb' }, c.suburb))),
      el('div', { class: 'badges' }, isRepeat(c, jobs) && badge('success', 'Repeat customer'), c.follow_up && badge('warning', `Follow up ${fmtDate(c.follow_up)}`)),
      el('dl', {},
        el('div', {}, el('dt', {}, 'Jobs'), el('dd', {}, String(cj.length))),
        el('div', {}, el('dt', {}, 'Total paid'), el('dd', {}, t.jobs ? money0(t.revenue) : '—')),
        el('div', {}, el('dt', {}, 'Found us via'), el('dd', {}, c.lead_source)))),
    el('div', { class: 'ccard-foot' },
      el('span', {}, c.phone || 'No phone recorded'),
      el('span', { class: 'btn-row' },
        el('button', { type: 'button', class: 'icon-btn', 'aria-label': `Edit ${c.name}`, onclick: () => editCustomer(c) }, icon('pencil', 14)),
        el('button', { type: 'button', class: 'icon-btn', 'aria-label': `Delete ${c.name}`, onclick: () => deleteCustomer(c) }, icon('trash', 14)))));
  li.addEventListener('dragstart', (e) => {
    e.dataTransfer.setData('text/plain', c.id);
    e.dataTransfer.effectAllowed = 'move';
    li.classList.add('dragging');
  });
  li.addEventListener('dragend', () => li.classList.remove('dragging'));
  return li;
}

function column(stage, items) {
  const list = el('ul', { class: 'column-list' },
    items.length === 0 ? el('li', { class: 'column-empty' }, 'No customers') : items.map(card));
  const section = el('section', { class: `column col-${stage.id}`, 'aria-label': `${stage.label} column` },
    el('header', { class: 'column-head' }, el('h2', {}, stage.label), el('span', { class: 'count' }, String(items.length))), list);
  section.addEventListener('dragover', (e) => {
    e.preventDefault();
    section.classList.add('drag-over');
  });
  section.addEventListener('dragleave', (e) => {
    if (!section.contains(e.relatedTarget)) section.classList.remove('drag-over');
  });
  section.addEventListener('drop', (e) => {
    e.preventDefault();
    section.classList.remove('drag-over');
    moveCustomer(e.dataTransfer.getData('text/plain'), stage.id);
  });
  return section;
}

function render() {
  document.getElementById('subtitle').textContent = `${customers.length} customers. Drag a card to another column to change its status.`;
  const visible = customers.filter(matches);
  const columns = BOOT.stages.filter((s) => stageFilter.value === 'ALL' || s.id === stageFilter.value);
  view.replaceChildren(
    ...(visible.length === 0 ? [emptyState('No customers match these filters', 'Try a different search or clear the filters.')] : []),
    el('div', { class: 'board' }, columns.map((s) => column(s, visible.filter((c) => c.stage === s.id).sort((a, b) => b.since.localeCompare(a.since))))),
  );
}

// ---- Dialogs ----

function openDetail(id) {
  const c = customers.find((x) => x.id === id);
  if (!c) return;
  const cj = customerJobs(c, jobs);
  const t = totals(cj);
  const row = (label, value) => el('div', {}, el('dt', {}, label), el('dd', {}, value || 'Not recorded'));
  openModal({ title: c.name, wide: true }, (body, { close }) => {
    body.append(el('div', { class: 'detail' },
      el('div', { class: 'detail-col' },
        el('section', { class: 'profile' }, avatar(c.name, 'lg'),
          el('div', {}, el('p', { class: 'strong' }, c.name), el('p', { class: 'muted' }, c.suburb),
            el('div', { class: 'badges' }, badge('info', stageLabel(c.stage)), isRepeat(c, jobs) && badge('success', 'Repeat customer')))),
        el('dl', { class: 'facts' }, row('Phone', c.phone), row('Email', c.email), row('Street address', c.address), row('How they found us', c.lead_source),
          row('Follow-up', c.follow_up ? fmtDate(c.follow_up) : ''), row('Customer since', fmtDate(c.since))),
        c.notes && el('section', {}, el('h3', { class: 'h3' }, 'Notes'), el('p', { class: 'pre' }, c.notes)),
        el('div', { class: 'btn-row' },
          el('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => editCustomer(c) }, icon('pencil', 14), 'Edit'),
          el('button', { type: 'button', class: 'btn btn-danger btn-sm', onclick: () => deleteCustomer(c) }, icon('trash', 14), 'Delete'))),
      el('section', {},
        el('div', { class: 'history-head' }, el('h3', { class: 'h3' }, `Job history (${cj.length})`),
          el('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: () => addJob(c.id) }, icon('plus', 14), 'Add job')),
        cj.length > 0 && el('p', { class: 'muted' }, `${money0(t.revenue)} paid · ${money0(t.profit)} profit · ${pct(t.margin)} margin`),
        cj.length === 0
          ? el('p', { class: 'column-empty' }, 'No jobs yet for this customer.')
          : el('ul', { class: 'stack' }, cj.map((j) => el('li', { class: 'job-item' },
              el('div', {}, el('a', { href: url(`jobs?edit=${encodeURIComponent(j.job_id)}`) }, `${j.job_id} · ${j.scope}`), el('strong', {}, isDone(j) ? money0(jobMetrics(j).totalPaid) : money0(j.quote))),
              el('p', {}, `${j.material} · ${j.length_m} m · ${fmtDate(j.job_start)}${isDone(j) ? ` · profit ${money0(jobMetrics(j).profit)}` : ` · quote (${j.status.toLowerCase().replace('_', ' ')})`}`)))))));
  });
}

function addJob(customerId) {
  openJobForm({
    customerId,
    customers,
    onSaved: (job) => {
      jobs = [...jobs, job];
      render();
    },
    onClose: (reason) => reason === 'dismissed' && openDetail(customerId),
  });
}

function editCustomer(c) {
  openCustomerForm({
    customer: c,
    onSaved: (saved) => {
      customers = customers.map((x) => (x.id === saved.id ? saved : x));
      render();
    },
  });
}

async function deleteCustomer(c) {
  const n = customerJobs(c, jobs).length;
  const warning = n ? ` This also permanently deletes their ${n} job${n === 1 ? '' : 's'}.` : '';
  const ok = await confirmDialog({ title: 'Delete customer', message: `Delete ${c.name}?${warning} This cannot be undone.`, confirmLabel: 'Delete customer' });
  if (!ok) return;
  try {
    await api.remove('customers', c.id);
    customers = customers.filter((x) => x.id !== c.id);
    jobs = jobs.filter((j) => j.customer_id !== c.id);
    toast('success', `${c.name} deleted`);
    render();
  } catch (err) {
    toast('error', err.message);
  }
}

document.getElementById('add-customer').addEventListener('click', () =>
  openCustomerForm({
    onSaved: (saved) => {
      customers = [saved, ...customers];
      render();
      openDetail(saved.id);
    },
  }),
);
[q, stageFilter, sourceFilter].forEach((input) => input.addEventListener(input === q ? 'input' : 'change', render));

(async () => {
  try {
    [{ customers }, { jobs }] = await Promise.all([api.list('customers'), api.list('jobs')]);
    render();
  } catch (err) {
    view.replaceChildren(errorState(err));
  }
})();
