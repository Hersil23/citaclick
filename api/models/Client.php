<?php

require_once __DIR__ . '/../config/database.php';

class Client
{
    public static function findByBusiness(int $businessId, array $filters = []): array
    {
        $db = Database::getInstance();
        $where = ['c.business_id = :bid'];
        $params = [':bid' => $businessId];

        if (!empty($filters['search'])) {
            $where[] = '(c.name LIKE :s OR c.phone LIKE :s2 OR c.email LIKE :s3)';
            $params[':s'] = '%' . $filters['search'] . '%';
            $params[':s2'] = '%' . $filters['search'] . '%';
            $params[':s3'] = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        try {
            $stmt = $db->prepare("
                SELECT c.*,
                       (SELECT COUNT(*) FROM appointments WHERE client_id = c.id) AS total_visits,
                       (SELECT MAX(date) FROM appointments WHERE client_id = c.id AND status = 'completed') AS last_visit
                FROM clients c
                WHERE {$whereStr}
                ORDER BY c.name ASC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Fallback: appointments table may not exist yet
            $stmt = $db->prepare("
                SELECT c.*, 0 AS total_visits, NULL AS last_visit
                FROM clients c
                WHERE {$whereStr}
                ORDER BY c.name ASC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
    }

    public static function findById(int $id, int $businessId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM clients WHERE id = :id AND business_id = :bid LIMIT 1');
        $stmt->execute([':id' => $id, ':bid' => $businessId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO clients (business_id, name, phone, email, notes, created_at)
            VALUES (:bid, :name, :phone, :email, :notes, NOW())
        ');
        $stmt->execute([
            ':bid'   => $data['business_id'],
            ':name'  => $data['name'],
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'phone', 'email', 'notes', 'photo'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $stmt = $db->prepare('UPDATE clients SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public static function delete(int $id, int $businessId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM clients WHERE id = :id AND business_id = :bid');
        return $stmt->execute([':id' => $id, ':bid' => $businessId]);
    }

    public static function findOrCreateByPhone(int $businessId, string $name, string $phone, ?string $email = null): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM clients WHERE business_id = :bid AND phone = :phone LIMIT 1');
        $stmt->execute([':bid' => $businessId, ':phone' => $phone]);
        $row = $stmt->fetch();

        if ($row) return (int)$row['id'];

        return self::create([
            'business_id' => $businessId,
            'name'        => $name,
            'phone'       => $phone,
            'email'       => $email,
        ]);
    }
}
