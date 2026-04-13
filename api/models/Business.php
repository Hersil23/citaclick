<?php

require_once __DIR__ . '/../config/database.php';

class Business
{
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*, p.slug AS plan_slug, p.name AS plan_name,
                   s.status AS sub_status, s.start_date, s.end_date
            FROM businesses b
            LEFT JOIN subscriptions s ON s.business_id = b.id AND s.status = "active"
            LEFT JOIN plans p ON p.id = s.plan_id
            WHERE b.id = :id LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM businesses WHERE slug = :slug AND status = "active" LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'description', 'logo', 'address', 'phone', 'theme',
                     'instagram', 'facebook', 'whatsapp', 'google_maps_url',
                     'currency', 'price_mode', 'exchange_rate', 'status', 'business_type'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $fields[] = 'updated_at = NOW()';
        $stmt = $db->prepare('UPDATE businesses SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }
}
