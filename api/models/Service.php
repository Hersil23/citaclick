<?php

require_once __DIR__ . '/../config/database.php';

class Service
{
    public static function findByBusiness(int $businessId, ?int $categoryId = null): array
    {
        $db = Database::getInstance();
        $where = 's.business_id = :bid';
        $params = [':bid' => $businessId];

        if ($categoryId) {
            $where .= ' AND s.category_id = :cid';
            $params[':cid'] = $categoryId;
        }

        $stmt = $db->prepare("
            SELECT s.*, sc.name AS category_name
            FROM services s
            LEFT JOIN service_categories sc ON sc.id = s.category_id
            WHERE {$where}
            ORDER BY sc.name ASC, s.name ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $businessId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*, sc.name AS category_name
            FROM services s
            LEFT JOIN service_categories sc ON sc.id = s.category_id
            WHERE s.id = :id AND s.business_id = :bid
            LIMIT 1
        ');
        $stmt->execute([':id' => $id, ':bid' => $businessId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO services (business_id, category_id, name, description, duration, price, image, status, created_at)
            VALUES (:bid, :cid, :name, :desc, :dur, :price, :img, "active", NOW())
        ');
        $stmt->execute([
            ':bid'   => $data['business_id'],
            ':cid'   => $data['category_id'] ?? null,
            ':name'  => $data['name'],
            ':desc'  => $data['description'] ?? null,
            ':dur'   => $data['duration'],
            ':price' => $data['price'],
            ':img'   => $data['image'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'description', 'duration', 'price', 'image', 'category_id', 'status'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $stmt = $db->prepare('UPDATE services SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM services WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public static function getCategories(int $businessId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM service_categories WHERE business_id = :bid ORDER BY name');
        $stmt->execute([':bid' => $businessId]);
        return $stmt->fetchAll();
    }

    public static function createCategory(int $businessId, string $name): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO service_categories (business_id, name, created_at) VALUES (:bid, :name, NOW())');
        $stmt->execute([':bid' => $businessId, ':name' => $name]);
        return (int)$db->lastInsertId();
    }
}
