"""HTTP flow tests. Needs: python3 + requests, php. Run: python3 tests/flows.py
Starts php -S on a temp database (HTTP allowed for the test only) and exercises login, CSRF, CRUD, validation, injection and access rules."""
import os, re, subprocess, sys, tempfile, time, requests

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
db = tempfile.mktemp(suffix='.sqlite')
env = dict(os.environ, EB_ALLOW_HTTP='1', EB_SQLITE_PATH=db)
PORT = 8765
B = f'http://127.0.0.1:{PORT}'
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(1.2)
fails = 0
def check(name, ok, detail=''):
    global fails
    print(('PASS ' if ok else 'FAIL ') + name + ('' if ok else '  ' + str(detail)))
    fails += 0 if ok else 1

def csrf(html): return re.search(r'name="csrf" value="([0-9a-f]+)"', html).group(1)
def boot_token(s):
    html = s.get(B + '/').text
    return re.search(r'"csrf":"([0-9a-f]+)"', html).group(1)

try:
    s = requests.Session()
    r = s.get(B + '/', allow_redirects=False)
    check('fresh install redirects to setup', r.status_code == 302 and r.headers['Location'].endswith('/setup'))
    r = s.get(B + '/setup'); tok = csrf(r.text)
    r = s.post(B + '/setup', data={'csrf': tok, 'username': 'admin', 'password': 'short', 'password2': 'short'})
    check('setup rejects short password', 'at least 10' in r.text)
    r = s.post(B + '/setup', data={'csrf': tok, 'username': 'admin', 'password': 'correct-horse-9', 'password2': 'correct-horse-9', 'load_sample': '1'}, allow_redirects=False)
    check('setup creates admin and loads sample', r.status_code == 302)
    check('setup page switches itself off', requests.get(B + '/setup').status_code == 404)

    anon = requests.Session()
    check('anonymous API call is 401', anon.get(B + '/api/jobs.php').status_code == 401)
    check('anonymous page redirects to login', anon.get(B + '/jobs', allow_redirects=False).headers.get('Location', '').endswith('/login'))

    # Login, wrong password, rate limit
    tok = csrf(s.get(B + '/login').text)
    r = s.post(B + '/login', data={'csrf': 'bad', 'username': 'admin', 'password': 'correct-horse-9'})
    check('login without valid CSRF is refused', 'expired' in r.text)
    for i in range(5):
        r = s.post(B + '/login', data={'csrf': tok, 'username': 'admin', 'password': 'wrong-' + str(i)})
    check('wrong password shows an error', 'Wrong username or password' in r.text or 'Too many' in r.text)
    r = s.post(B + '/login', data={'csrf': tok, 'username': 'admin', 'password': 'correct-horse-9'})
    check('rate limit blocks even the right password after 5 failures', 'Too many failed attempts' in r.text)
    import sqlite3
    c = sqlite3.connect(db); c.execute('DELETE FROM login_attempts'); c.commit(); c.close()
    r = s.post(B + '/login', data={'csrf': tok, 'username': 'admin', 'password': 'correct-horse-9'}, allow_redirects=False)
    check('login succeeds after the block is cleared', r.status_code == 302)
    cookie = r.headers.get('Set-Cookie', '')
    check('session cookie is HttpOnly and SameSite=Strict', 'HttpOnly' in cookie and 'SameSite=Strict' in cookie, cookie)

    token = boot_token(s)
    H = {'X-CSRF-Token': token, 'Content-Type': 'application/json'}
    for p in ('/', '/customers', '/jobs', '/reports'):
        r = s.get(B + p)
        check(f'page {p} loads with CSP header', r.status_code == 200 and "script-src 'self'" in r.headers.get('Content-Security-Policy', ''), r.status_code)
    check('security headers present', r.headers.get('X-Frame-Options') == 'DENY' and r.headers.get('X-Content-Type-Options') == 'nosniff' and 'Referrer-Policy' in r.headers)
    check('GET lists 49 jobs / 40 customers', len(s.get(B + '/api/jobs.php').json()['jobs']) == 49 and len(s.get(B + '/api/customers.php').json()['customers']) == 40)

    # CSRF
    cust = {'name': 'Test Person', 'suburb': 'Berwick', 'phone': '0400 000 000', 'stage': 'ENQUIRY', 'lead_source': 'Google', 'email': '', 'address': '', 'follow_up': '', 'notes': ''}
    r = s.post(B + '/api/customers.php', json=cust)
    check('write without CSRF token is 403', r.status_code == 403, r.status_code)
    r = s.post(B + '/api/customers.php', json=cust, headers={'X-CSRF-Token': 'x' * 64})
    check('write with wrong CSRF token is 403', r.status_code == 403)
    r = s.post(B + '/api/customers.php', data='name=x', headers={'X-CSRF-Token': token})
    check('non-JSON write is refused', r.status_code == 415, r.status_code)

    # Customer CRUD
    r = s.post(B + '/api/customers.php', json=cust, headers=H)
    check('create customer', r.status_code == 201 and r.json()['customer']['id'] == 'C041', r.text)
    cid = r.json()['customer']['id']
    r = s.post(B + '/api/customers.php', json={**cust, 'name': '', 'stage': 'X'}, headers=H)
    check('invalid customer gives 422 with field errors', r.status_code == 422 and 'name' in r.json()['errors'] and 'stage' in r.json()['errors'])
    r = s.put(B + f'/api/customers.php?id={cid}', json={**cust, 'stage': 'QUOTED', 'follow_up': '2026-11-02'}, headers=H)
    check('drag/drop style update (stage change)', r.status_code == 200 and r.json()['customer']['stage'] == 'QUOTED')

    # XSS and SQL injection payloads are stored as plain text
    xss = '<img src=x onerror=alert(1)>'
    r = s.post(B + '/api/customers.php', json={**cust, 'name': xss, 'notes': "x'); DROP TABLE jobs;--"}, headers=H)
    check('XSS/SQLi text accepted as data', r.status_code == 201)
    xid = r.json()['customer']['id']
    check('jobs table still intact after injection attempt', len(s.get(B + '/api/jobs.php').json()['jobs']) == 49)
    r = s.get(B + '/api/reports.php', params={'category': 'JOBS', 'q': "' OR '1'='1"})
    check('SQLi in report search returns no rows', r.status_code == 200 and r.json()['rows'] == [])
    check('unknown report category is 400', s.get(B + '/api/reports.php', params={'category': "X'; --"}).status_code == 400)
    check('bad year is 400', s.get(B + '/api/reports.php', params={'category': 'JOBS', 'year': "2024' OR 1=1"}).status_code == 400)
    check('delete customer id with SQL is 404', s.delete(B + "/api/customers.php?id=C001' OR '1'='1", headers={'X-CSRF-Token': token}).status_code == 404)
    check('customers page HTML never contains the raw XSS payload', xss not in s.get(B + '/customers').text)
    s.delete(B + f'/api/customers.php?id={xid}', headers={'X-CSRF-Token': token})

    # Job CRUD
    job = {'customer_id': cid, 'suburb': 'Berwick', 'scope': 'Colorbond fence', 'type': 'Boundary', 'material': 'Colorbond', 'sleepers': 'No Sleepers', 'length_m': 20, 'height_m': 1.8,
           'quote': 3000, 'deposit': 1500, 'final_pay': 1500, 'job_start': '2026-10-01', 'job_end': '2026-10-03', 'days_on_site': 2, 'workers': True, 'hours': 10, 'worker_cost': 400, 'material_cost': 1200,
           'lead_source': 'Google', 'status': 'COMPLETED', 'notes': ''}
    r = s.post(B + '/api/jobs.php', json=job, headers=H)
    check('create job', r.status_code == 201 and r.json()['job']['job_id'] == 'J050' and r.json()['job']['workers'] is True, r.text)
    r = s.post(B + '/api/jobs.php', json={**job, 'customer_id': 'C999', 'length_m': 0, 'job_end': '2026-09-01'}, headers=H)
    check('invalid job gives 422', r.status_code == 422 and {'customer_id', 'length_m', 'job_end'} <= set(r.json()['errors']), r.text)
    r = s.put(B + '/api/jobs.php?id=J050', json={**job, 'quote': 3500, 'notes': 'edited'}, headers=H)
    check('update job', r.status_code == 200 and r.json()['job']['quote'] == 3500 and r.json()['job']['notes'] == 'edited')
    check('update unknown job is 404', s.put(B + '/api/jobs.php?id=J999', json=job, headers=H).status_code == 404)
    check('jobs/customers pages open direct-link forms (?new=1, ?edit=)', s.get(B + '/jobs?new=1').status_code == 200 and s.get(B + '/jobs?edit=J012').status_code == 200)

    # Reports + CSV
    rep = s.get(B + '/api/reports.php', params={'category': 'MATERIALS'}).json()
    check('report materials summary has revenue 172209', abs(rep['summary'][1]['value'] - (172209 + 3000)) < 1, rep['summary'])
    r = s.get(B + '/api/reports.php', params={'category': 'SOURCES', 'format': 'csv'})
    check('CSV export', r.status_code == 200 and r.headers['Content-Type'].startswith('text/csv') and 'attachment' in r.headers['Content-Disposition'] and r.text.splitlines()[0].lstrip('\ufeff').startswith('Name,Group,Profit'))

    # Cascade delete
    r = s.delete(B + f'/api/customers.php?id={cid}', headers={'X-CSRF-Token': token})
    check('delete customer cascades to jobs', r.status_code == 200 and r.json()['jobs_deleted'] == 1 and s.get(B + '/api/jobs.php').json()['jobs'].__len__() == 49)
    check('delete job', s.delete(B + '/api/jobs.php?id=J001', headers={'X-CSRF-Token': token}).status_code == 200)

    # Logout needs POST + CSRF
    s.get(B + '/logout')
    check('GET /logout does not sign out', s.get(B + '/api/jobs.php').status_code == 200)
    token = boot_token(s)
    s.post(B + '/logout', data={'csrf': token})
    check('POST /logout with CSRF signs out', s.get(B + '/api/jobs.php').status_code == 401)

    # Direct file access: only /public is served by the server root
    for p in ('/../storage/earthbender.sqlite', '/app/config.php', '/storage/earthbender.sqlite', '/database/schema.sql'):
        check(f'{p} is not downloadable', requests.get(B + p).status_code in (404, 403, 400))
finally:
    srv.terminate()
    for f in (db,):
        if os.path.exists(f): os.remove(f)
print('\nAll flow checks passed' if not fails else f'\n{fails} check(s) FAILED')
sys.exit(1 if fails else 0)
