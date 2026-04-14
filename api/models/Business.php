<?php

require_once __DIR__ . '/../config/database.php';

class Business
{
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*, p.name AS plan_name, p.price_monthly, p.max_providers,
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
        $stmt = $db->prepare('SELECT * FROM businesses WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'description', 'logo_url', 'address', 'phone', 'theme',
                     'instagram', 'facebook', 'whatsapp', 'google_maps_url',
                     'currency_code', 'currency_mode', 'exchange_rate', 'status', 'business_type'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $stmt = $db->prepare('UPDATE businesses SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }
}
