<?php
declare(strict_types=1);

// Login POST handler. Bootstrap is loaded by index.php before including this.

$password = $_POST['password'] ?? '';
if (auth_attempt((string)$password)) {
    header('Location: ' . url('/'));
    exit;
}
header('Location: ' . url('/login?err=' . urlencode('Wrong password.')));
exit;
