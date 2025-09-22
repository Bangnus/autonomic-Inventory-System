<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_login();
$user = $_SESSION['user'];

// Redirect to role-specific dashboards for unified layout
if (($user['role'] ?? '') === 'admin') {
    header('Location: /autonomic/admin/dashboard.php');
    exit;
}

header('Location: /autonomic/user/dashboard.php');
exit;


