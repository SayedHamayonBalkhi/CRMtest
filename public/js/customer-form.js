// Add / edit customer dialog.
import { api, BOOT } from './api.js';
import { disclosure, el, field, openModal, selectInput, textArea, textInput, toast } from './ui.js';

const MORE_KEYS = ['email', 'address', 'follow_up', 'notes'];

export function openCustomerForm({ customer = null, onSaved }) {
  openModal({ title: customer ? 'Edit customer' : 'Add customer' }, (body, { close }) => {
    const c = customer ?? { name: '', phone: '', suburb: '', stage: 'ENQUIRY', lead_source: 'Google', email: '', address: '', follow_up: '', notes: '' };
    const f = {
      name: field('cust-name', 'Customer name *', textInput({ value: c.name })),
      suburb: field('cust-suburb', 'Suburb *', textInput({ value: c.suburb })),
      phone: field('cust-phone', 'Phone', textInput({ type: 'tel', value: c.phone })),
      stage: field('cust-stage', 'Status', selectInput(BOOT.stages, c.stage)),
      lead_source: field('cust-source', 'How they found us', selectInput(BOOT.leadSources, c.lead_source)),
      email: field('cust-email', 'Email', textInput({ type: 'email', value: c.email })),
      address: field('cust-address', 'Street address', textInput({ value: c.address })),
      follow_up: field('cust-followup', 'Follow-up date', textInput({ type: 'date', value: c.follow_up })),
      notes: field('cust-notes', 'Notes', textArea(c.notes)),
    };
    const more = disclosure({
      label: 'Contact & site details',
      hint: 'Email, street address, follow-up, notes',
      open: Boolean(customer && (c.email || c.address || c.follow_up || c.notes)),
      children: MORE_KEYS.map((k) => f[k].el),
    });

    const read = () => Object.fromEntries(Object.entries(f).map(([k, x]) => [k, x.control.value.trim()]));

    function check(v) {
      const e = {};
      if (!v.name) e.name = 'Customer name is required.';
      if (!v.suburb) e.suburb = 'Suburb is required.';
      if (v.phone && !/^[0-9+()\s-]{6,}$/.test(v.phone)) e.phone = 'Enter a valid phone number.';
      if (v.email && !/^\S+@\S+\.\S+$/.test(v.email)) e.email = 'Enter a valid email address.';
      return e;
    }

    function show(errors) {
      Object.entries(f).forEach(([k, x]) => x.setError(errors[k]));
      if (MORE_KEYS.some((k) => errors[k])) more.set(true);
      body.querySelector('[aria-invalid="true"]')?.focus();
    }

    const submit = el('button', { type: 'submit', class: 'btn btn-primary' }, customer ? 'Save changes' : 'Add customer');
    const form = el(
      'form',
      { class: 'stack', novalidate: true },
      f.name.el,
      el('div', { class: 'form-grid two' }, f.suburb.el, f.phone.el, f.stage.el, f.lead_source.el),
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
        const res = customer ? await api.update('customers', customer.id, values) : await api.create('customers', values);
        toast('success', customer ? 'Customer updated' : 'Customer added');
        onSaved?.(res.customer);
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
