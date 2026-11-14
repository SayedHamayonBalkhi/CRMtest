"""Browser tests (optional; needs python3 + playwright + chromium and a running server with the sample data).
Usage: python3 tests/ui.py http://127.0.0.1:8770 admin 'password' [screenshot-dir]"""
import sys, os
from playwright.sync_api import sync_playwright

B, USER, PW = sys.argv[1], sys.argv[2], sys.argv[3]
SHOTS = sys.argv[4] if len(sys.argv) > 4 else None
fails = 0


def check(name, ok, detail=''):
    global fails
    print(('PASS ' if ok else 'FAIL ') + name + ('' if ok else '  ' + str(detail)), flush=True)
    fails += 0 if ok else 1


def shot(pg, name):
    if SHOTS:
        os.makedirs(SHOTS, exist_ok=True)
        pg.screenshot(path=f'{SHOTS}/{name}.png', full_page=True)


def login(pg):
    pg.goto(B + '/login'); pg.fill('#username', USER); pg.fill('#password', PW)
    pg.click('button[type=submit]'); pg.wait_for_url(B + '/')


with sync_playwright() as p:
    b = p.chromium.launch()
    ctx = b.new_context(viewport={'width': 1440, 'height': 900}, accept_downloads=True)
    pg = ctx.new_page(); pg.set_default_timeout(7000)
    problems = []
    pg.on('console', lambda m: problems.append(m.text) if m.type in ('error', 'warning') else None)
    pg.on('pageerror', lambda e: problems.append(str(e)))
    login(pg)

    # ---- Customers ----
    pg.goto(B + '/customers'); pg.wait_for_selector('.board')
    check('board has 5 columns', pg.locator('.column').count() == 5)
    check('40 cards in total', pg.locator('.ccard').count() == 40)
    pg.click('#add-customer')
    check('dialog opens with focus inside', pg.locator('[role=dialog] :focus').count() == 1)
    check('secondary fields hidden by default', not pg.locator('#cust-email').is_visible())
    pg.click('[role=dialog] button[type=submit]')
    check('required-field errors shown', pg.locator('.field-error:visible').count() >= 2)
    pg.click('.disclosure > button')
    check('Contact & site details expands', pg.locator('#cust-email').is_visible())
    pg.fill('#cust-name', 'UI Test Customer'); pg.fill('#cust-suburb', 'Hallam'); pg.fill('#cust-phone', '0400 123 456'); pg.fill('#cust-email', 'bad')
    pg.click('[role=dialog] button[type=submit]')
    check('invalid email blocks save', pg.locator('#cust-email-error').is_visible())
    pg.fill('#cust-email', 'ui@example.com'); pg.select_option('#cust-stage', 'QUOTED')
    pg.click('[role=dialog] button[type=submit]')
    pg.wait_for_selector('.toast.success')
    check('customer added and detail dialog opens', pg.locator('[role=dialog] h2:has-text("UI Test Customer")').is_visible())
    pg.keyboard.press('Escape')
    check('Escape closes dialog', pg.locator('[role=dialog]').count() == 0)
    check('card appears in Quote sent column', pg.locator('.col-QUOTED .ccard:has-text("UI Test Customer")').count() == 1)

    pg.locator('.ccard:has-text("UI Test Customer")').drag_to(pg.locator('.col-BOOKED .column-list'))
    pg.wait_for_selector('.toast:has-text("moved to Job booked")')
    check('drag and drop moved the card', pg.locator('.col-BOOKED .ccard:has-text("UI Test Customer")').count() == 1)
    pg.reload(); pg.wait_for_selector('.board')
    check('drag result persisted after reload', pg.locator('.col-BOOKED .ccard:has-text("UI Test Customer")').count() == 1)

    pg.fill('#f-query', 'hallam')
    check('search narrows cards', 0 < pg.locator('.ccard').count() < 40)
    pg.fill('#f-query', ''); pg.select_option('#f-stage', 'BOOKED')
    check('status filter shows one column', pg.locator('.column').count() == 1)
    pg.select_option('#f-stage', 'ALL')

    pg.locator('.ccard:has-text("UI Test Customer") .ccard-main').click()
    pg.click('[role=dialog] button:has-text("Add job")')
    check('job form pre-selects the customer', pg.locator('#job-customer').input_value() != '')
    check('labour section collapsed for a new job', not pg.locator('#job-wcost').is_visible())
    pg.fill('#job-scope', 'UI test fence'); pg.fill('#job-length', '10'); pg.fill('#job-quote', '2000'); pg.select_option('#job-status', 'COMPLETED')
    pg.fill('#job-deposit', '1000'); pg.fill('#job-final', '1000')
    pg.click('.disclosure > button'); pg.fill('#job-wcost', '300'); pg.fill('#job-mcost', '700')
    pg.click('[role=dialog] button[type=submit]'); pg.wait_for_selector('.toast:has-text("Job added")')
    check('returns to customer detail showing the new job', pg.locator('[role=dialog] .job-item:has-text("UI test fence")').is_visible())
    pg.keyboard.press('Escape')
    pg.locator('.ccard:has-text("UI Test Customer") [aria-label^="Delete"]').click()
    check('delete message mentions the jobs', 'also permanently deletes their 1 job' in pg.locator('[role=dialog] p').inner_text())
    pg.click('[role=dialog] button.btn-danger'); pg.wait_for_selector('.toast:has-text("deleted")')
    check('customer removed', pg.locator('.ccard:has-text("UI Test Customer")').count() == 0)

    # ---- Jobs ----
    pg.goto(B + '/jobs'); pg.wait_for_selector('table')
    check('49 job rows', pg.locator('tbody tr').count() == 49)
    check('6 Check data badges', pg.locator('.badge:has-text("Check data")').count() == 6)
    pg.select_option('#f-material', 'Timber')
    check('material filter works', 0 < pg.locator('tbody tr').count() < 49)
    check('clear filters appears', pg.locator('#clear-filters').is_visible())
    pg.click('#clear-filters')
    check('clear filters resets', pg.locator('tbody tr').count() == 49)
    pg.select_option('#f-sort', 'profit'); first = pg.locator('tbody tr:first-child td:first-child').inner_text()
    check('sort by profit puts J030 first', first == 'J030', first)
    pg.goto(B + '/jobs?new=1'); pg.wait_for_selector('[role=dialog]')
    check('?new=1 opens the add job form', pg.locator('[role=dialog] h2').inner_text() == 'Add job')
    pg.keyboard.press('Escape')
    pg.goto(B + '/jobs?edit=J012'); pg.wait_for_selector('[role=dialog]')
    check('?edit=J012 opens that job with labour section open', 'J012' in pg.locator('[role=dialog] h2').inner_text() and pg.locator('#job-wcost').is_visible())
    pg.fill('#job-notes', 'edited in browser'); pg.click('[role=dialog] button[type=submit]'); pg.wait_for_selector('.toast:has-text("Job updated")')
    check('url cleaned after opening form', pg.url.endswith('/jobs'))
    pg.fill('#f-query', 'J012'); pg.locator('[aria-label="Delete job J012"]').click()
    pg.click('[role=dialog] button.btn-danger'); pg.wait_for_selector('.toast:has-text("deleted")')
    check('job deleted', pg.locator('tbody tr').count() == 0)

    # ---- Reports ----
    pg.goto(B + '/reports'); pg.wait_for_selector('.bars')
    pg.select_option('#f-category', 'CUSTOMERS'); pg.wait_for_selector('th:has-text("Last job")')
    check('customers report lists 40 rows', pg.locator('tbody tr').count() == 40)
    pg.select_option('#f-category', 'SOURCES'); pg.wait_for_selector('th:has-text("Finished")'); pg.select_option('#f-year', '2024'); pg.wait_for_timeout(500)
    check('year filter reduces rows', 0 < pg.locator('tbody tr').count() < 48)
    with pg.expect_download() as d:
        pg.click('#export-csv')
    check('CSV downloads', d.value.suggested_filename == 'earthbender-sources-2024.csv', d.value.suggested_filename)

    # ---- Theme, screenshots, responsive ----
    pg.click('[data-theme-choice=dark]')
    check('dark theme applied and saved', pg.evaluate("document.documentElement.dataset.theme") == 'dark' and pg.evaluate("localStorage.getItem('eb-theme')") == 'dark')
    pages = (('dashboard', '/'), ('customers', '/customers'), ('jobs', '/jobs'), ('reports', '/reports'))
    for name, path in pages:
        pg.goto(B + path); pg.wait_for_timeout(600); shot(pg, f'{name}-dark')
    pg.click('[data-theme-choice=light]')
    for name, path in pages:
        pg.goto(B + path); pg.wait_for_timeout(600); shot(pg, f'{name}-light')

    mctx = b.new_context(viewport={'width': 390, 'height': 800}); mp = mctx.new_page(); mp.set_default_timeout(7000)
    login(mp)
    for name, path in pages:
        mp.goto(B + path); mp.wait_for_timeout(700); shot(mp, f'mobile-{name}')
        check(f'mobile {name}: page does not scroll sideways', mp.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"), mp.evaluate("document.documentElement.scrollWidth"))
    mp.click('#menu-btn')
    check('mobile drawer opens', mp.locator('#sidebar.is-open').count() == 1)
    shot(mp, 'mobile-drawer')
    check('no console errors or CSP violations', not problems, problems)
    b.close()

print('\nAll browser checks passed' if not fails else f'\n{fails} check(s) FAILED')
sys.exit(1 if fails else 0)
