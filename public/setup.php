<?php
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/view.php';
require APP_ROOT . '/app/seed.php';

// First-run page: creates the tables and the admin user. Once a user exists it answers 404 and does nothing.
$pdo = db();
if (db_ready() && has_users()) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$errors = [];
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $min = cfg()['password_min_length'];
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
        $errors[] = 'Username must be 3 to 60 characters: letters, numbers, dot, dash or underscore.';
    }
    if (mb_strlen($password) < $min) {
        $errors[] = "Password must be at least $min characters.";
    }
    if ($password !== (string)($_POST['password2'] ?? '')) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors) {
        db_init($pdo);
        $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
        if (!empty($_POST['load_sample'])) {
            seed_database($pdo);
        }
        header('Location: ' . url('login'));
        exit;
    }
}

auth_header('Set up');
?>
  <h1>First-time setup</h1>
  <p class="muted">Create the admin account. This page switches itself off once an account exists.</p>
  <?php foreach ($errors as $err): ?><p class="form-error" role="alert"><?= e($err) ?></p><?php endforeach; ?>
  <form method="post" action="<?= e(url('setup')) ?>" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?= auth_field('username', 'Username', 'text', 'autocomplete="username" required autofocus value="' . e($username) . '"') ?>
    <?= auth_field('password', 'Password (at least ' . cfg()['password_min_length'] . ' characters)', 'password', 'autocomplete="new-password" required') ?>
    <?= auth_field('password2', 'Repeat password', 'password', 'autocomplete="new-password" required') ?>
    <label class="check"><input type="checkbox" name="load_sample" value="1" checked> Load the Earthbender sample data (49 jobs, 40 customers)</label>
    <button type="submit" class="btn btn-primary">Create account</button>
  </form>
<?php auth_footer();
