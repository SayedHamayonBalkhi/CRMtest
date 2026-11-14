// Shared UI helpers: element builder, icons, badges, toast, modal, confirm dialog, collapsible section, form fields.
// Everything is built with createElement/textContent, never innerHTML, so stored data cannot inject markup.
import { BOOT } from './api.js';
import { initials } from './format.js';

const SVG_NS = 'http://www.w3.org/2000/svg';

export function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs ?? {})) {
    if (value === false || value == null) continue;
    if (key === 'class') node.className = value;
    else if (key.startsWith('on') && typeof value === 'function') node.addEventListener(key.slice(2), value);
    else if (value === true) node.setAttribute(key, '');
    else node.setAttribute(key, value);
  }
  for (const child of children.flat(Infinity)) {
    if (child == null || child === false) continue;
    node.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
  return node;
}

export function icon(name, size = 18) {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('class', 'icon');
  svg.setAttribute('width', size);
  svg.setAttribute('height', size);
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const use = document.createElementNS(SVG_NS, 'use');
  use.setAttribute('href', `${BOOT.base}/assets/icons.svg#${name}`);
  svg.append(use);
  return svg;
}

export const badge = (tone, text, attrs = {}) => el('span', { class: `badge tone-${tone}`, ...attrs }, text);
export const avatar = (name, size = '') => el('span', { class: `avatar ${size}`.trim(), 'aria-hidden': 'true' }, initials(name));

export function metricCard({ label, value, hint, href }) {
  const inner = [el('p', { class: 'metric-label' }, label), el('p', { class: 'metric-value' }, value), hint && el('p', { class: 'metric-hint' }, hint)];
  return href ? el('a', { class: 'card metric', href }, inner) : el('div', { class: 'card metric' }, inner);
}

export function emptyState(title, text) {
  return el('div', { class: 'empty' }, el('strong', {}, title), el('p', {}, text));
}

// Horizontal bar. value is 0 to 100. Width is set through the CSSOM because the page's CSP blocks inline style attributes.
export function bar(value) {
  const fill = el('div');
  fill.style.width = `${Math.max(0, Math.min(100, value))}%`;
  return el('div', { class: 'bar', role: 'progressbar', 'aria-valuenow': Math.round(value), 'aria-valuemin': 0, 'aria-valuemax': 100 }, fill);
}

export function barRow(left, right, value) {
  return el('li', {}, el('div', { class: 'bar-row' }, el('span', {}, left), el('span', {}, right)), bar(value));
}

export function errorState(err) {
  return el('div', { class: 'empty' }, el('strong', {}, 'Could not load the data'), el('p', {}, err.message || 'Try reloading the page.'));
}

// ---- Toast ----

export function toast(tone, message) {
  const box = document.getElementById('toasts');
  const item = el(
    'div',
    { class: `toast ${tone}`, role: 'status' },
    icon(tone === 'success' ? 'check-circle' : 'alert-circle'),
    el('p', {}, message),
    el('button', { type: 'button', 'aria-label': 'Dismiss', onclick: () => item.remove() }, icon('x', 16)),
  );
  box.append(item);
  setTimeout(() => item.remove(), 3800);
}

// ---- Modal: one at a time, Escape closes, Tab stays inside, focus returns to the opener ----

let active = null;
let seq = 0;
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// build(body, { close }) fills the dialog. onClose(reason) runs after it closes: 'dismissed' or 'replaced' (another dialog opened).
export function openModal({ title, wide = false, onClose }, build) {
  if (active) active.close('replaced');
  const opener = document.activeElement;
  const titleId = `dialog-title-${++seq}`;
  const body = el('div', { class: 'dialog-body' });
  const dialog = el(
    'div',
    { class: `dialog${wide ? ' wide' : ''}`, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId, tabindex: '-1' },
    el('header', { class: 'dialog-head' }, el('h2', { id: titleId }, title), el('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Close', onclick: () => close('dismissed') }, icon('x'))),
    body,
  );
  const overlay = el('div', { class: 'overlay', onmousedown: (e) => e.target === overlay && close('dismissed') }, dialog);

  function onKey(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      close('dismissed');
    } else if (e.key === 'Tab') {
      const items = [...dialog.querySelectorAll(FOCUSABLE)];
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  function close(reason = 'dismissed') {
    if (active !== handle) return;
    active = null;
    document.removeEventListener('keydown', onKey, true);
    overlay.remove();
    document.body.classList.remove('modal-open');
    if (reason === 'dismissed' && opener?.isConnected) opener.focus();
    onClose?.(reason);
  }

  const handle = { close };
  active = handle;
  document.addEventListener('keydown', onKey, true);
  document.body.classList.add('modal-open');
  document.body.append(overlay);
  build(body, handle);
  (body.querySelector('[autofocus]') ?? body.querySelector('input, select, textarea') ?? dialog).focus();
  return handle;
}

export function confirmDialog({ title, message, confirmLabel }) {
  return new Promise((resolve) => {
    openModal({ title, onClose: () => resolve(false) }, (body, { close }) => {
      body.append(
        el('p', { class: 'muted' }, message),
        el(
          'div',
          { class: 'form-actions' },
          el('button', { type: 'button', class: 'btn btn-secondary', autofocus: true, onclick: () => close('dismissed') }, 'Cancel'),
          el('button', { type: 'button', class: 'btn btn-danger', onclick: () => { resolve(true); close('dismissed'); } }, confirmLabel),
        ),
      );
    });
  });
}

// ---- Collapsible section for secondary form fields ----

let disclosureSeq = 0;
export function disclosure({ label, hint, open = false, children }) {
  const bodyId = `disclosure-${++disclosureSeq}`;
  const body = el('div', { class: 'disclosure-body', id: bodyId, hidden: !open }, children);
  const button = el('button', { type: 'button', 'aria-expanded': String(open), 'aria-controls': bodyId }, el('span', {}, label, hint && el('span', { class: 'hint' }, hint)), icon('chevron-down', 16));
  const set = (isOpen) => {
    button.setAttribute('aria-expanded', String(isOpen));
    body.hidden = !isOpen;
  };
  button.addEventListener('click', () => set(body.hidden));
  return { el: el('div', { class: 'disclosure' }, button, body), set };
}

// ---- Form fields: a label, a control and an error line ----

export function field(id, label, control) {
  const error = el('p', { class: 'field-error', role: 'alert', id: `${id}-error`, hidden: true });
  control.id = id;
  control.setAttribute('aria-describedby', error.id);
  return {
    el: el('div', { class: 'field' }, el('label', { class: 'label', for: id }, label), control, error),
    control,
    setError(message) {
      error.textContent = message || '';
      error.hidden = !message;
      control.classList.toggle('has-error', Boolean(message));
      control.setAttribute('aria-invalid', message ? 'true' : 'false');
    },
  };
}

export function textInput({ type = 'text', value = '', autocomplete } = {}) {
  const input = el('input', { type, class: 'input', autocomplete: autocomplete ?? 'off' });
  if (type === 'number') {
    input.setAttribute('inputmode', 'decimal');
    input.setAttribute('step', 'any');
  }
  input.value = value;
  return input;
}

// options: strings or { id, label }. A current value that is not in the list is kept as an extra option.
export function selectInput(options, value) {
  const items = options.map((o) => (typeof o === 'string' ? { id: o, label: o } : o));
  if (value !== '' && !items.some((o) => o.id === value)) items.push({ id: value, label: value });
  const select = el('select', { class: 'input' }, items.map((o) => el('option', { value: o.id }, o.label)));
  select.value = value;
  return select;
}

export function textArea(value = '') {
  const area = el('textarea', { class: 'input', rows: '3' });
  area.value = value;
  return area;
}

// Number from a text box: blank counts as 0, anything that is not a number is NaN.
export const num = (s) => (String(s).trim() === '' ? 0 : Number(s));
