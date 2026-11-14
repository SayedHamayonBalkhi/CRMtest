<?php
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/view.php';
require_login();
render_header('Dashboard', 'dashboard');
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted" id="period">Loading…</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('jobs?new=1')) ?>"><?= icon('plus', 16) ?> Add job</a>
</div>
<div id="view"></div>
<?php render_footer('dashboard');
