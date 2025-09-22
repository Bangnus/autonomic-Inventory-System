<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// Destroy session and redirect to login
session_destroy();
header('Location: /autonomic/login.php');
exit;
?>