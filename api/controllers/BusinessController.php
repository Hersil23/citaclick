<?php

require_once __DIR__ . '/../config/database.php';

class BusinessController
{
    public function show(array $args): void
    {
        $user = $args['user'];
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT * FROM businesses WHERE id = :bid LIMIT 1');
        $stmt->execute([':bid' => $user['business_id']]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        // Plan info (separate query to avoid JOIN failures)
        $business['plan_name'] = null;
        $business['end_date'] = null;
        try {
            $stmt = $db->prepare('
                SELECT p.name AS plan_name, s.end_date, s.start_date
                FROM subscriptions s
                JOIN plans p ON p.id = s.plan_id
                WHERE s.business_id = :bid AND s.status = "active"
                ORDER BY s.created_at DESC LIMIT 1
            ');
            $stmt->execute([':bid' => $user['business_id']]);
            $sub = $stmt->fetch();
            if ($sub) {
                $business['plan_name'] = $sub['plan_name'];
                $business['end_date'] = $sub['end_date'];
            }
        } catch (\PDOException $e) {
            // subscriptions or plans table may have different schema
        }

        sendJson(200, ['success' => true, 'data' => $business]);
    }

    public function changePlan(array $args): void
    {
        $user = $args['user'];
        $body = $args['body'];
        $planName = trim($body['plan'] ?? '');

        if (empty($planName)) {
            sendJson(400, ['success' => false, 'message' => 'Plan requerido']);
        }

        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id, name FROM plans WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $planName]);
        $plan = $stmt->fetch();

        if (!$plan) {
            sendJson(400, ['success' => false, 'message' => 'Plan no valido']);
        }

        // Deactivate current subscription
        $stmt = $db->prepare('UPDATE subscriptions SET status = "cancelled" WHERE business_id = :bid AND status = "active"');
        $stmt->execute([':bid' => $user['business_id']]);

        // Create new subscription with 21 day trial
        $trialEnd = date('Y-m-d H:i:s', strtotime('+21 days'));
        $stmt = $db->prepare('
            INSERT INTO subscriptions (business_id, plan_id, status, start_date, end_date, created_at)
            VALUES (:bid, :pid, "active", CURDATE(), :end_date, NOW())
        ');
        $stmt->execute([
            ':bid'      => $user['business_id'],
            ':pid'      => $plan['id'],
            ':end_date' => date('Y-m-d', strtotime('+21 days')),
        ]);

        // Log the change
        try {
            $stmt = $db->prepare('
                INSERT INTO subscription_history (subscription_id, to_plan_id, action, created_at)
                VALUES (:sid, :pid, "change", NOW())
            ');
            $stmt->execute([':sid' => $db->lastInsertId(), ':pid' => $plan['id']]);
        } catch (\PDOException $e) {
            // subscription_history may not exist
        }

        sendJson(200, [
            'success' => true,
            'message' => 'Plan actualizado a ' . $planName,
            'data' => ['plan' => $planName],
        ]);
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
                $fields[] = "`{$f}` = :{$f}";
                $params[":{$f}"] = $body[$f];
            }
        }

        if (empty($fields)) {
            sendJson(400, ['success' => false, 'message' => 'No hay campos para actualizar']);
        }
        $fieldStr = implode(', ', $fields);

        try {
            $stmt = $db->prepare("UPDATE businesses SET {$fieldStr} WHERE id = :bid");
            $stmt->execute($params);
        } catch (\PDOException $e) {
            sendJson(500, ['success' => false, 'message' => 'Error al actualizar', 'debug' => $e->getMessage()]);
        }

        $this->show($args);
    }
}
