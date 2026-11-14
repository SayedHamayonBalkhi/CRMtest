// Optional developer check (needs Node, not needed on the host): node tests/calc.test.mjs
// Confirms public/js/calc.js gives the same figures as the PHP code.
import { readFileSync } from 'node:fs';
import { totals, isRepeat } from '../public/js/calc.js';

const data = JSON.parse(readFileSync(new URL('../database/seed-data.json', import.meta.url)));
const t = totals(data.jobs);
const repeat = data.customers.filter((c) => isRepeat(c, data.jobs)).length;
const checks = [
  ['49 jobs', data.jobs.length === 49],
  ['40 customers', data.customers.length === 40],
  ['revenue 172209', Math.abs(t.revenue - 172209) < 0.5],
  ['profit 60220', Math.abs(t.profit - 60220) < 0.5],
  ['margin ~35%', Math.abs(t.margin - 0.35) < 0.005],
  ['profit/hour ~133', Math.abs(t.profitPh - 133) < 0.5],
  ['10 repeat customers', repeat === 10],
];
let failed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
  if (!ok) failed++;
}
process.exit(failed ? 1 : 0);
