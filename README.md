# Earthbender Fencing – customer panel (PHP)

A small customer, job and profit panel for Earthbender Fencing. Plain HTML, CSS and JavaScript in the browser, PHP 8.1+ on the server, SQLite by default (MySQL by changing one config file). No Node, no build step, no frameworks, no CDN scripts.

## Requirements

- PHP 8.1 or newer (developed and tested on PHP 8.3.6)
- PHP extensions: `pdo_sqlite` (or `pdo_mysql` if you use MySQL), `mbstring`, `json`, `session` (all standard on cPanel hosts)
- HTTPS on the domain (the panel redirects HTTP to HTTPS and marks the session cookie `Secure`)
- Apache with `mod_rewrite` and `.htaccess` enabled (standard on cPanel). On other servers, route `/customers`, `/jobs`, `/reports`, `/login`, `/logout`, `/setup` to the matching `public/*.php` file.

## Folder layout

```
public/     web root: index.php (dashboard), customers.php, jobs.php, reports.php, login.php, logout.php, setup.php, api/, css/, js/, assets/
app/        PHP code: config.php (the one config file), bootstrap, validation, calculations, reports, models
database/   schema.sql, seed.php (sample data import), seed-data.json
storage/    SQLite file and PHP sessions (created at first run; must be writable)
tests/      automated checks (optional, not needed on the live host)
```

## Install on cPanel

**Option A (recommended): point the domain at `public/`.**
1. Upload the whole project folder to your home directory, outside `public_html` (for example `/home/youruser/earthbender/`).
2. In cPanel → Domains, set the document root of the domain or subdomain to `/home/youruser/earthbender/public`.
3. Make `storage/` writable by PHP (permissions 755 or 775, owner = your account).
4. Open `https://your-domain/setup` straight away (see "Create the admin user").

**Option B: everything inside `public_html`** (when you cannot change the document root).
1. Upload the whole project into `public_html`. The root `.htaccess` blocks `app/`, `database/`, `storage/`, `tests/` and forwards all other requests into `public/`.
2. Make `storage/` writable and open `https://your-domain/setup`.
Option B relies on the `.htaccess` rules; after uploading, check that `https://your-domain/storage/` and `https://your-domain/app/config.php` give an error page (403/404), not a file.

## Create the admin user

Open `/setup` once. It creates the tables, creates the admin account (password at least 10 characters, stored with `password_hash`) and can load the Earthbender sample data. After the first account exists the page answers 404 and does nothing. Do this immediately after uploading, because until then anyone who finds the URL could create the account.

There is one admin user. To change the password later, delete the row from the `users` table and temporarily allow `/setup` again, or update `password_hash` with a hash made by `php -r 'echo password_hash("new password", PASSWORD_DEFAULT);'`.

## Import the sample data

The sample data (49 jobs, 40 customers, Jun 2023 to Aug 2024) is in `database/seed-data.json`. Load it in either way:
- tick "Load the Earthbender sample data" on the setup page, or
- run `php database/seed.php` from the command line. It is re-runnable: existing job and customer IDs are left alone. `php database/seed.php --force` resets those IDs to the sample values (this overwrites edits made to them).

The seed file is generated from the data that was in the v8 prototype (`lib/real-data.ts`). Reading the `.xlsx` file directly is not implemented.

## Switch to MySQL

1. Create an empty MySQL database and user in cPanel.
2. In `app/config.php` set `'driver' => 'mysql'` and fill in `mysql_host`, `mysql_name`, `mysql_user`, `mysql_pass`.
3. Open `/setup` (or run `php database/seed.php`) to create the tables. `database/schema.sql` runs unchanged on SQLite and MySQL 5.7+/MariaDB.
There is no tool to copy existing SQLite data into MySQL. Either start with the sample data, or re-enter the records.

## Backups

- SQLite: copy `storage/earthbender.sqlite` (cPanel → Backup, or download it by FTP while nobody is saving). Keep copies off the server.
- MySQL: use cPanel → phpMyAdmin → Export, or `mysqldump`.
- The data is personal customer information. Store backups somewhere private, and back up before every upgrade.

## Security summary

Login with hashed password, session cookie `HttpOnly` + `Secure` + `SameSite=Strict`, session ID regenerated at login, failed logins limited to 5 per IP per 15 minutes (change in `app/config.php`), CSRF token on every write (and on login/logout/setup forms), prepared statements only, output escaped (the browser UI builds elements with `textContent`), Content-Security-Policy with no external hosts, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, HSTS, and `.htaccess` deny rules for `app/`, `database/`, `storage/`, `tests/`.

## Running the tests (optional)

```
php tests/run.php                       # seed data totals, reports, CSV, validation, cascade delete (PHP only)
python3 tests/flows.py                  # login, CSRF, CRUD, SQL injection, XSS, file access (needs python3 + requests + php)
node tests/calc.test.mjs                # confirms public/js/calc.js matches the PHP figures (needs Node, tests only)
python3 tests/ui.py URL USER PASS DIR   # browser flows + screenshots (needs playwright + chromium and a running server)
```
For a local run: `EB_ALLOW_HTTP=1 php -S 127.0.0.1:8000 -t public public/router.php`. `EB_ALLOW_HTTP=1` switches off the HTTPS redirect and Secure cookie for local testing only; never set it on a live host.
