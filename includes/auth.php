<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function find_user_by_email(string $email): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function create_user(string $name, string $email, string $password, string $role = 'user'): int {
    $pdo = get_pdo();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
    $stmt->execute([$name, $email, $hash, $role]);
    return (int)$pdo->lastInsertId();
}

function authenticate(string $email, string $password): ?array {
    $user = find_user_by_email($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return null;
}

?>


