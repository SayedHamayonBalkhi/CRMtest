<?php
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/view.php';
require_login();
render_header('Jobs', 'jobs');
?>
<div class="page-head">
  <div>
    <h1>Jobs</h1>
    <p class="muted" id="subtitle">Loading…</p>
  </div>
  <button type="button" class="btn btn-primary" id="add-job"><?= icon('plus', 16) ?> Add job</button>
</div>
<div class="grid cols-4" id="summary"></div>
<div class="filters">
  <?= search_input('f-query', 'Search job, customer, scope or suburb') ?>
  <?= select_filter('f-status', 'Filter by status', options_html(JOB_STATUSES, 'All statuses')) ?>
  <?= select_filter('f-type', 'Filter by work type', options_html(same_keys(JOB_TYPES), 'All work types')) ?>
  <?= select_filter('f-material', 'Filter by material', options_html(same_keys(MATERIALS), 'All materials')) ?>
  <?= select_filter('f-source', 'Filter by lead source', options_html(same_keys(LEAD_SOURCES), 'All lead sources')) ?>
  <?= select_filter('f-sort', 'Sort by', '<option value="newest">Sort: newest</option><option value="value">Sort: job value</option><option value="profit">Sort: profit</option><option value="margin">Sort: margin</option>') ?>
  <button type="button" class="btn btn-secondary" id="clear-filters" hidden>Clear filters</button>
</div>
<div id="view"></div>
<?php render_footer('jobs');
