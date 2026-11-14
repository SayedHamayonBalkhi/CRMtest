<?php
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/view.php';
require_login();
render_header('Reports', 'reports');
?>
<div class="page-head">
  <div>
    <h1>Reports</h1>
    <p class="muted" id="subtitle">Loading…</p>
  </div>
  <a class="btn btn-secondary" id="export-csv" href="#" aria-disabled="true"><?= icon('download', 16) ?> Export CSV</a>
</div>
<div class="filters">
  <?= select_filter('f-category', 'Report category', '') ?>
  <?= select_filter('f-year', 'Year', '<option value="ALL">All years</option>') ?>
  <?= search_input('f-query', 'Search by name or group') ?>
  <?= select_filter('f-sort', 'Sort by', '<option value="amount">Sort: amount</option><option value="name">Sort: name</option><option value="date">Sort: date</option>') ?>
</div>
<div class="grid cols-4" id="summary"></div>
<div id="view"></div>
<?php render_footer('reports');
