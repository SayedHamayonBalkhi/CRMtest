-- Portable schema: runs unchanged on SQLite and MySQL 5.7+/MariaDB.
-- Dates are stored as 'YYYY-MM-DD' text, or '' when empty. Money is AUD.
-- Calculated values (total paid, profit, margin, per-metre figures) are NOT stored.

CREATE TABLE IF NOT EXISTS users (
    username VARCHAR(60) NOT NULL PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    created_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
    ip VARCHAR(45) NOT NULL,
    attempted_at INT NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id VARCHAR(10) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    suburb VARCHAR(80) NOT NULL,
    lead_source VARCHAR(60) NOT NULL,
    stage VARCHAR(12) NOT NULL,
    email VARCHAR(190) NOT NULL,
    address VARCHAR(190) NOT NULL,
    follow_up VARCHAR(10) NOT NULL,
    notes TEXT NOT NULL,
    since VARCHAR(10) NOT NULL
);

CREATE TABLE IF NOT EXISTS jobs (
    job_id VARCHAR(10) NOT NULL PRIMARY KEY,
    customer_id VARCHAR(10) NOT NULL,
    suburb VARCHAR(80) NOT NULL,
    scope VARCHAR(255) NOT NULL,
    type VARCHAR(40) NOT NULL,
    material VARCHAR(40) NOT NULL,
    sleepers VARCHAR(30) NOT NULL,
    length_m DECIMAL(8,2) NOT NULL,
    height_m DECIMAL(6,2) NOT NULL,
    quote DECIMAL(10,2) NOT NULL,
    deposit DECIMAL(10,2) NOT NULL,
    final_pay DECIMAL(10,2) NOT NULL,
    job_start VARCHAR(10) NOT NULL,
    job_end VARCHAR(10) NOT NULL,
    days_on_site INT NOT NULL,
    workers TINYINT NOT NULL,
    hours DECIMAL(8,2) NOT NULL,
    worker_cost DECIMAL(10,2) NOT NULL,
    material_cost DECIMAL(10,2) NOT NULL,
    lead_source VARCHAR(60) NOT NULL,
    status VARCHAR(12) NOT NULL,
    notes TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
