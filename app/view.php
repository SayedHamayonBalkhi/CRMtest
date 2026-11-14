<?php
// Page shell shared by the Dashboard, Customers, Jobs and Reports pages.

const NAV_ITEMS = [
    'dashboard' => ['label' => 'Dashboard', 'path' => '', 'icon' => 'layout-dashboard'],
    'customers' => ['label' => 'Customers', 'path' => 'customers', 'icon' => 'user-round'],
    'jobs' => ['label' => 'Jobs', 'path' => 'jobs', 'icon' => 'hammer'],
    'reports' => ['label' => 'Reports', 'path' => 'reports', 'icon' => 'bar-chart'],
];

function icon(string $name, int $size = 18): string
{
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><use href="' . e(url('assets/icons.svg')) . '#' . e($name) . '"/></svg>';
}

function boot_data(): array
{
    $list = fn(array $m) => array_map(fn($id, $label) => ['id' => $id, 'label' => $label], array_keys($m), array_values($m));
    return [
        'base' => base_path(),
        'csrf' => csrf_token(),
        'today' => date('Y-m-d'),
        'stages' => $list(CUSTOMER_STAGES),
        'jobStatuses' => $list(JOB_STATUSES),
        'jobTypes' => JOB_TYPES,
        'materials' => MATERIALS,
        'sleepers' => SLEEPER_OPTIONS,
        'leadSources' => LEAD_SOURCES,
        'reportCategories' => $list(REPORT_CATEGORIES),
    ];
}

function render_header(string $title, string $active): void
{
    $nav = NAV_ITEMS[$active];
    $boot = json_encode(boot_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    ?>
<!doctype html>
<html lang="en-AU" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Earthbender Fencing</title>
<script src="<?= e(url('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(url('css/app.css')) ?>">
<link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="theme-panel">
<script type="application/json" id="boot"><?= $boot ?></script>
<div class="app">
  <div class="backdrop" aria-hidden="true"></div>
  <div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
      <div class="brand">
        <svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true"><rect x="2" y="12" width="6" height="12" rx="2" fill="#793B80"/><rect x="10" y="6" width="6" height="18" rx="2" fill="#91CFD1"/><rect x="18" y="2" width="6" height="22" rx="2" fill="#FF805B"/></svg>
        <span class="brand-name">Earthbender</span>
      </div>
      <nav class="nav">
        <?php foreach (NAV_ITEMS as $key => $item): ?>
          <a href="<?= e(url($item['path'])) ?>" class="nav-link<?= $key === $active ? ' is-active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?> aria-label="<?= e($item['label']) ?>" title="<?= e($item['label']) ?>">
            <?= icon($item['icon']) ?><span class="nav-label"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>
    <button type="button" class="scrim" id="scrim" aria-label="Close navigation" hidden></button>
    <div class="main-col">
      <header class="topbar">
        <button type="button" class="icon-btn" id="menu-btn" aria-label="Toggle navigation" aria-controls="sidebar"><?= icon('menu', 20) ?></button>
        <nav class="breadcrumb" aria-label="Breadcrumb">Panel / <strong><?= e($nav['label']) ?></strong></nav>
        <div class="topbar-actions">
          <div class="theme-toggle" role="group" aria-label="Colour theme">
            <button type="button" data-theme-choice="light" aria-pressed="true" aria-label="Light mode"><?= icon('sun', 14) ?><span>Light</span></button>
            <button type="button" data-theme-choice="dark" aria-pressed="false" aria-label="Dark mode"><?= icon('moon', 14) ?><span>Dark</span></button>
          </div>
          <form method="post" action="<?= e(url('logout')) ?>" class="logout-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="icon-btn" aria-label="Sign out" title="Sign out"><?= icon('log-out', 20) ?></button>
          </form>
        </div>
      </header>
      <main class="main" id="main">
<?php
}

function render_footer(string $script): void
{
    ?>
      </main>
    </div>
  </div>
  <div class="toasts" id="toasts" aria-live="polite"></div>
</div>
<script type="module" src="<?= e(url('js/' . $script . '.js')) ?>"></script>
</body>
</html>
<?php
}

function options_html(array $options, string $allLabel): string
{
    $out = '<option value="ALL">' . e($allLabel) . '</option>';
    foreach ($options as $value => $label) {
        $out .= '<option value="' . e((string)$value) . '">' . e($label) . '</option>';
    }
    return $out;
}

// Turns ['Colorbond', 'Timber'] into ['Colorbond' => 'Colorbond', 'Timber' => 'Timber'] for options_html().
function same_keys(array $list): array
{
    return array_combine($list, $list);
}

function select_filter(string $id, string $label, string $inner): string
{
    return '<label class="filter"><span class="sr-only">' . e($label) . '</span><select id="' . e($id) . '" class="input" aria-label="' . e($label) . '">' . $inner . '</select></label>';
}

function search_input(string $id, string $placeholder): string
{
    return '<label class="search"><span class="sr-only">' . e($placeholder) . '</span>' . icon('search', 16)
        . '<input type="search" id="' . e($id) . '" class="input" placeholder="' . e($placeholder) . '" autocomplete="off"></label>';
}

// Standalone pages (sign in, first-run setup) without the panel shell.
function auth_header(string $title): void
{
    ?>
<!doctype html>
<html lang="en-AU" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Earthbender Fencing</title>
<script src="<?= e(url('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(url('css/app.css')) ?>">
<link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="theme-panel auth-body">
<main class="auth-card">
  <div class="brand">
    <svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true"><rect x="2" y="12" width="6" height="12" rx="2" fill="#793B80"/><rect x="10" y="6" width="6" height="18" rx="2" fill="#91CFD1"/><rect x="18" y="2" width="6" height="22" rx="2" fill="#FF805B"/></svg>
    <span class="brand-name">Earthbender</span>
  </div>
<?php
}

function auth_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function auth_field(string $id, string $label, string $type, string $extra = ''): string
{
    return '<div class="field"><label for="' . e($id) . '" class="label">' . e($label) . '</label><input id="' . e($id) . '" name="' . e($id) . '" type="' . e($type) . '" class="input" ' . $extra . '></div>';
}
