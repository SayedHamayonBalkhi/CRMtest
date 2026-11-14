<?php
require __DIR__ . '/../app/bootstrap.php';

// Sign out is a POST with a CSRF token, so another site cannot sign the user out with a link or image.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    logout();
}
header('Location: ' . url('login'));
