<?php

require_once __DIR__ . '/../config/database.php';

class BusinessController
{
    public function show(array $args): void
    {
        $user = $args['user'];
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT b.*, p.slug AS plan_slug, p.name AS plan_name,
                   s.status AS sub_status, s.start_date, s.end_date
            FROM businesses b
            LEFT JOIN subscriptions s ON s.business_id = b.id AND s.status = "active"
            LEFT JOIN plans p ON p.id = s.plan_id
            WHERE b.id = :bid
            LIMIT 1
        ');
        $stmt->execute([':bid' => $user['business_id']]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        sendJson(200, ['success' => true, 'data' => $business]);
    }

    public function update(array $args): void
    {
        $user = $args['user'];
        $body = $args['body'];
        $db = Database::getInstance();

        $fields = [];
        $params = [':bid' => $user['business_id']];

        $allowed = ['name', 'description', 'logo', 'address', 'phone', 'theme',
                     'instagram', 'facebook', 'whatsapp', 'google_maps_url',
                     'currency', 'price_mode', 'exchange_rate'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $body[$f];
            }
        }

        if (empty($fields)) {
            sendJson(400, ['success' => false, 'message' => 'No hay campos para actualizar']);
        }

        $fields[] = 'updated_at = NOW()';
        $fieldStr = implode(', ', $fields);

        $stmt = $db->prepare("UPDATE businesses SET {$fieldStr} WHERE id = :bid");
        $stmt->execute($params);

        $this->show($args);
    }
}
