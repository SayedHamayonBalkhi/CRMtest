// Add / edit job dialog. Shared by the Jobs and Customers pages.
import { api, BOOT } from './api.js';
import { disclosure, el, field, num, openModal, selectInput, textArea, textInput, toast } from './ui.js';

const NUMERIC = ['length_m', 'height_m', 'quote', 'deposit', 'final_pay', 'days_on_site', 'hours', 'worker_cost', 'material_cost'];
const MORE_KEYS = ['workers', 'days_on_site', 'hours', 'worker_cost', 'material_cost', 'notes'];

// customers: all customers (for the picker). customerId pre-selects one when adding from a customer's dialog.
export function openJobForm({ job = null, customerId = '', customers, onSaved, onClose }) {
  openModal({ title: job ? `Edit job ${job.job_id}` : 'Add job', wide: true, onClose }, (body, { close }) => {
    const preset = customers.find((c) => c.id === (job?.customer_id ?? customerId));
    const j = job ?? {
      customer_id: customerId, suburb: preset?.suburb ?? '', scope: '', type: 'Boundary', material: 'Colorbond', sleepers: 'No Sleepers',
      length_m: '', height_m: 1.8, quote: '', deposit: 0, final_pay: 0, job_start: '', job_end: '', status: 'QUOTED',
      lead_source: preset?.lead_source ?? 'Google', workers: false, days_on_site: 0, hours: 0, worker_cost: 0, material_cost: 0, notes: '',
    };
    const n = (v, type = 'number') => textInput({ type, value: String(v) });

    const customerSelect = el('select', { class: 'input' }, el('option', { value: '' }, 'Select a customer'),
      [...customers].sort((a, b) => a.name.localeCompare(b.name)).map((c) => el('option', { value: c.id }, `${c.name} (${c.suburb})`)));
    customerSelect.value = j.customer_id;

    const f = {
      customer_id: field('job-customer', 'Customer *', customerSelect),
      suburb: field('job-suburb', 'Job suburb *', textInput({ value: j.suburb })),
      scope: field('job-scope', 'Scope of work *', textInput({ value: j.scope })),
      type: field('job-type', 'Work type', selectInput(BOOT.jobTypes, j.type)),
      material: field('job-material', 'Material', selectInput(BOOT.materials, j.material)),
      sleepers: field('job-sleepers', 'Sleepers', selectInput(BOOT.sleepers, j.sleepers)),
      length_m: field('job-length', 'Length (m) *', n(j.length_m)),
      height_m: field('job-height', 'Height (m)', n(j.height_m)),
      status: field('job-status', 'Status', selectInput(BOOT.jobStatuses, j.status)),
      quote: field('job-quote', 'Quote ($)', n(j.quote)),
      deposit: field('job-deposit', 'Deposit paid ($)', n(j.deposit)),
      final_pay: field('job-final', 'Final payment ($)', n(j.final_pay)),
      job_start: field('job-start', 'Job start', n(j.job_start, 'date')),
      job_end: field('job-end', 'Job end', n(j.job_end, 'date')),
      lead_source: field('job-source', 'Lead source', selectInput(BOOT.leadSources, j.lead_source)),
      workers: field('job-workers', 'Hired workers?', selectInput(['No', 'Yes'], j.workers ? 'Yes' : 'No')),
      days_on_site: field('job-days', 'Days on site', n(j.days_on_site)),
      hours: field('job-hours', 'Hours worked', n(j.hours)),
      worker_cost: field('job-wcost', 'Worker cost ($)', n(j.worker_cost)),
      material_cost: field('job-mcost', 'Material cost ($)', n(j.material_cost)),
      notes: field('job-notes', 'Notes', textArea(j.notes)),
    };

    // Picking a customer fills the suburb when it is still empty.
    customerSelect.addEventListener('change', () => {
      const c = customers.find((x) => x.id === customerSelect.value);
      if (c && !f.suburb.control.value.trim()) f.suburb.control.value = c.suburb;
    });

    const more = disclosure({
      label: 'Labour, materials & notes',
      hint: 'Needed for profit figures',
      open: Boolean(job),
      children: [el('div', { class: 'form-grid two' }, MORE_KEYS.filter((k) => k !== 'notes').map((k) => f[k].el)), f.notes.el],
    });

    function read() {
      const v = {};
      for (const [k, x] of Object.entries(f)) v[k] = x.control.value.trim();
      NUMERIC.forEach((k) => (v[k] = num(v[k])));
      v.workers = v.workers === 'Yes';
      return v;
    }

    function check(v) {
      const e = {};
      if (!v.customer_id) e.customer_id = 'Choose a customer.';
      if (!v.scope) e.scope = 'Describe the work.';
      if (!v.suburb) e.suburb = 'Suburb is required.';
      NUMERIC.forEach((k) => {
        if (Number.isNaN(v[k]) || v[k] < 0) e[k] = 'Enter 0 or more.';
      });
      if (!e.length_m && v.length_m <= 0) e.length_m = 'Enter the length in metres.';
      if (v.job_start && v.job_end && v.job_end < v.job_start) e.job_end = 'End date is before the start date.';
      return e;
    }

    function show(errors) {
      Object.entries(f).forEach(([k, x]) => x.setError(errors[k]));
      if (MORE_KEYS.some((k) => errors[k])) more.set(true);
      body.querySelector('[aria-invalid="true"]')?.focus();
    }

    const submit = el('button', { type: 'submit', class: 'btn btn-primary' }, job ? 'Save changes' : 'Add job');
    const form = el(
      'form',
      { class: 'stack', novalidate: true },
      el('div', { class: 'form-grid two' }, f.customer_id.el, f.suburb.el),
      f.scope.el,
      el('div', { class: 'form-grid three' }, ['type', 'material', 'sleepers', 'length_m', 'height_m', 'status', 'quote', 'deposit', 'final_pay', 'job_start', 'job_end', 'lead_source'].map((k) => f[k].el)),
      more.el,
      el('div', { class: 'form-actions' }, el('button', { type: 'button', class: 'btn btn-secondary', onclick: () => close('dismissed') }, 'Cancel'), submit),
    );

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const values = read();
      const errors = check(values);
      show(errors);
      if (Object.keys(errors).length) return toast('error', 'Please fix the highlighted fields.');
      submit.disabled = true;
      try {
        const res = job ? await api.update('jobs', job.job_id, values) : await api.create('jobs', values);
        toast('success', job ? 'Job updated' : 'Job added');
        onSaved?.(res.job);
        close('dismissed');
      } catch (err) {
        if (Object.keys(err.errors ?? {}).length) show(err.errors);
        toast('error', err.message);
      } finally {
        submit.disabled = false;
      }
    });
    body.append(form);
  });
}
