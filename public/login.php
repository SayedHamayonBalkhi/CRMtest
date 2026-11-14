<?php
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/view.php';

if (!db_ready() || !has_users()) {
    header('Location: ' . url('setup'));
    exit;
}
if (current_user()) {
    header('Location: ' . url(''));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wait = login_blocked_minutes();
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($wait > 0) {
        $error = "Too many failed attempts. Try again in $wait minute" . ($wait === 1 ? '' : 's') . '.';
    } elseif (try_login(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
        header('Location: ' . url(''));
        exit;
    } else {
        $error = 'Wrong username or password.';
    }
}

auth_header('Sign in');
?>
  <h1>Sign in</h1>
  <p class="muted">Earthbender Fencing customer panel</p>
  <?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('login')) ?>" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?= auth_field('username', 'Username', 'text', 'autocomplete="username" required autofocus') ?>
    <?= auth_field('password', 'Password', 'password', 'autocomplete="current-password" required') ?>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
<?php auth_footer();
