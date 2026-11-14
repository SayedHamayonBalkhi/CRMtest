// Display formatting. Dates arrive as ISO (YYYY-MM-DD); money is AUD shown as $1,234.
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const aud0 = new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD', maximumFractionDigits: 0 });

export function fmtDate(iso) {
  if (!iso) return '—';
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return `${d} ${MONTHS[m - 1]} ${y}`;
}

export const money0 = (n) => aud0.format(n);
export const pct = (n) => `${Math.round(n * 100)}%`;

export function initials(name) {
  return name.split(' ').map((p) => p[0] ?? '').slice(0, 2).join('').toUpperCase();
}
