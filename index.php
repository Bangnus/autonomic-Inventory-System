<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (!empty($_SESSION['user'])) {
    header('Location: /autonomic/dashboard.php');
} else {
    header('Location: /autonomic/login.php');
}
exit;


