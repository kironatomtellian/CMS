<?php
declare(strict_types=1);

// Login POST handler. Bootstrap is loaded by index.php before including this.

$password = $_POST['password'] ?? '';
if (auth_attempt((string)$password)) {
    header('Location: ' . url('/'));
    exit;
}
$wait = auth_locked_for();
$msg = $wait > 0
    ? 'Too many wrong passwords. Try again in ' . (int)ceil($wait / 60) . ' minutes.'
    : 'Wrong password.';
header('Location: ' . url('/login?err=' . urlencode($msg)));
exit;
