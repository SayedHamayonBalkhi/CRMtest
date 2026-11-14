<?php
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/view.php';
require_login();
render_header('Customers', 'customers');
?>
<div class="page-head">
  <div>
    <h1>Customers</h1>
    <p class="muted" id="subtitle">Loading…</p>
  </div>
  <button type="button" class="btn btn-primary" id="add-customer"><?= icon('plus', 16) ?> Add customer</button>
</div>
<div class="filters">
  <?= search_input('f-query', 'Search name, suburb, phone or job') ?>
  <?= select_filter('f-stage', 'Filter by status', options_html(CUSTOMER_STAGES, 'All statuses')) ?>
  <?= select_filter('f-source', 'Filter by lead source', options_html(same_keys(LEAD_SOURCES), 'All lead sources')) ?>
</div>
<div id="view"></div>
<?php render_footer('customers');
