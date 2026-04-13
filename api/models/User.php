<?php

require_once __DIR__ . '/../config/database.php';

class User
{
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByBusiness(int $businessId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id, name, email, role, phone, avatar_url, is_active, created_at FROM users WHERE business_id = :bid ORDER BY name');
        $stmt->execute([':bid' => $businessId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO users (business_id, name, email, password_hash, phone, role, created_at)
            VALUES (:bid, :name, :email, :password_hash, :phone, :role, NOW())
        ');
        $stmt->execute([
            ':bid'      => $data['business_id'],
            ':name'     => $data['name'],
            ':email'    => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            ':phone'    => $data['phone'] ?? null,
            ':role'     => $data['role'] ?? 'provider',
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'email', 'phone', 'photo', 'role', 'status'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (!empty($data['password'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($fields)) return false;
        $fields[] = 'updated_at = NOW()';
        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }
}
