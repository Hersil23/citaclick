<?php
// TEMPORAL - borrar despues de usar
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$hash = password_hash('Miranda01@', PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE email = :email');
$stmt->execute([':hash' => $hash, ':email' => 'chefherasi@gmail.com']);

$stmt2 = $db->prepare('UPDATE users SET password_hash = :hash WHERE email = :email');
$stmt2->execute([':hash' => password_hash('Miranda01@', PASSWORD_BCRYPT, ['cost' => 12]), ':email' => 'herasidesweb@gmail.com']);

echo json_encode(['success' => true, 'message' => 'Passwords reset to Miranda01@']);
