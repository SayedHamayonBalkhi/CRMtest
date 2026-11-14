<?php
// Single configuration file. To move from SQLite to MySQL, set 'driver' => 'mysql' and fill in the mysql_* values.
return [
    'timezone' => 'Australia/Melbourne',

    'db' => [
        'driver' => 'mysql', // 'sqlite' or 'mysql'
        'sqlite_path' => __DIR__ . '/../storage/earthbender.sqlite',
        'mysql_host' => 'sql312.infinityfree.com',
        'mysql_port' => 3306,
        'mysql_name' => 'if0_43300111_mydatabase',
        'mysql_user' => 'if0_43300111',
        'mysql_pass' => 'bfJN2DC2vgqQf6i',
    ],

    // Redirect HTTP to HTTPS, send HSTS and mark the session cookie Secure.
    'force_https' => true,

    'session_name' => 'eb_session',

    // Failed logins allowed per IP address inside the window.
    'login_max_attempts' => 5,
    'login_window_minutes' => 15,

    'password_min_length' => 10,
];
