<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

if ($action === 'test') {
    try {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT 1');
        echo json_encode(['success' => true, 'message' => 'Database connected', 'php' => PHP_VERSION]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'superadmin') {
    $email = $_GET['email'] ?? 'admin@citaclick.net';
    $password = $_GET['pass'] ?? 'Admin2026!';
    $name = $_GET['name'] ?? 'Super Admin';

    try {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User already exists with this email']);
            exit;
        }

        $stmt = $db->prepare('SELECT id FROM businesses WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => 'citaclick-admin']);
        $biz = $stmt->fetch();

        if (!$biz) {
            $db->prepare('INSERT INTO businesses (name, slug, theme, business_type, status, created_at, updated_at) VALUES (:name, :slug, :theme, :type, "active", NOW(), NOW())')
               ->execute([':name' => 'CitaClick Admin', ':slug' => 'citaclick-admin', ':theme' => 'caballeros', ':type' => 'other']);
            $bizId = (int)$db->lastInsertId();
        } else {
            $bizId = (int)$biz['id'];
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare('INSERT INTO users (business_id, name, email, password, role, status, created_at, updated_at) VALUES (:bid, :name, :email, :pass, "superadmin", "active", NOW(), NOW())')
           ->execute([':bid' => $bizId, ':name' => $name, ':email' => $email, ':pass' => $hashed]);

        echo json_encode([
            'success' => true,
            'message' => 'Superadmin created',
            'email' => $email,
            'password' => $password,
            'note' => 'DELETE this file after setup!',
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['usage' => '?action=test (test DB) or ?action=superadmin&email=x&pass=x&name=x']);
