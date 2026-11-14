# Notes

## What this is
A rebuild of the v8 Next.js prototype as plain HTML/CSS/JS + PHP + SQLite/MySQL. Data now persists. Everything the spec asked to keep from v8 is listed in the checklist below.

## Decisions I made (change any of them)
- **Routing:** separate PHP pages (Dashboard, Customers, Jobs, Reports) with a shared header/footer, not hash routing. Why: `/jobs?new=1` and `/jobs?edit=J012` work as normal URLs, each page loads only its own JS, and there is no client router to maintain.
- **Auth:** one admin user (the spec's default). I did not build roles or multiple users.
- **First run:** a setup page (not a CLI script), because many cPanel plans have no SSH. It switches itself off once a user exists.
- **Data loading:** each page loads all customers and jobs (about 90 rows) and filters in the browser, exactly like v8. Reports and CSV are computed on the server. Both use the same formulas (`public/js/calc.js` and `app/calc.php`).
- **Reports:** the Customers report lists every customer, including ones with no completed jobs; the other reports list completed jobs only (as in v8). Dates in tables show as `3 Jun 2023`; stored and exported as ISO.
- **Lists** (work type, material, sleepers, lead source, statuses) are fixed lists in `app/constants.php`, and the server rejects values outside them.
- **Extras that were not in v8 but follow from the spec:** sign out button, login/setup pages, CSV text cells starting with `= + - @` are prefixed with `'` (spreadsheet formula injection), CSV has a UTF-8 BOM so Excel shows "·" correctly.

## What changed from v8
- Nothing is in memory any more: customers and jobs are stored in the database.
- Customers are stored rows (v8 derived them from jobs at load time). IDs are generated as C041, J050, ...
- Customer delete removes their jobs in one transaction, with a confirmation that states how many jobs go with them.
- Login is required (v8 had the password gate removed).

## Dropped on purpose (as the brief asked)
Pipeline, Team and AI Forecast pages; their fictional data; chat history in customers; simulated phone call; priority and assignee on customers; fictional notifications. The top-bar notification bell is also gone, because it only showed those fictional notifications. The sheet's empty columns: Email and Address are kept as optional fields, Learnings is dropped.

**Real data each dropped section would need if you want it later**
- Team: workers, role, hourly rate (and which jobs they worked).
- Pipeline: open leads with stage, expected value and date.
- Forecast: can be derived later from completed-job history (revenue per month, margin), no new data needed.

## v8 feature checklist (all ticked = present in this version)
- [x] Dashboard: revenue, profit, profit per hour, average job value, customers + repeat customers, "Where jobs come from", "Profit margin by material", recent jobs, follow-ups with empty state, Add job button, metric cards link to their pages
- [x] Customers: five-column board, HTML5 drag and drop changes status (saved), search (name, suburb, phone, email, address, job id/scope), status and lead source filters, empty state when nothing matches
- [x] Customer card: suburb, repeat badge, follow-up badge, job count, total paid, lead source, phone, edit and delete buttons
- [x] Customer detail dialog: facts, notes, job history with totals, Add job, edit, delete; returns to the detail after adding a job
- [x] Add / edit / delete customer; delete also deletes their jobs with a clear message
- [x] Jobs: table, search, filters (status, work type, material, lead source), sort (newest, value, profit, margin), clear filters, summary cards, add / edit / delete, "Check data" badge from the notes field
- [x] `/jobs?new=1` and `/jobs?edit=J012` open the form directly
- [x] Reports: six categories, year filter, search, sort, summary, breakdown bars, table, CSV export
- [x] Forms with collapsible secondary section ("Contact & site details", "Labour, materials & notes"), client and server validation, field errors, toasts
- [x] Shell: sidebar (collapses on desktop, drawer on mobile), top bar with breadcrumb, light/dark toggle saved in localStorage, keyboard-accessible dialogs (Escape, focus trap, focus returns, aria labels)
- [x] v8 look: beige/white/plum/navy/purple/turquoise/coral, dark theme, hand-written CSS with variables, inline SVG icons
- [x] Calculations not stored (total paid, total cost, profit, margin, per-metre, per-hour); money totals count completed jobs only
- [x] Source data warnings kept: J009, J019, J022, J031, J046, J047 show "Check data"

## Known limitations
- The `.xlsx` file and the v7 zip were not in the upload, so the seed comes from v8's `lib/real-data.ts` (same 49 jobs / 40 customers). There is no xlsx importer.
- Customers can be dragged with the mouse or touch-drag-capable browsers only; the keyboard way to change status is the Edit form. (Mobile browsers vary in HTML5 drag support.)
- One admin, no password reset page, no email.
- Not tested: Apache `.htaccess` behaviour (tests ran on PHP's built-in server), MySQL, Option B root `.htaccess`, Safari/Firefox.
- All sample customers have status "Job completed" (that is what the sheet says), so the first three board columns are empty until you add new ones.
