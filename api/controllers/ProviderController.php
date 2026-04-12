<?php

require_once __DIR__ . '/../models/Provider.php';
require_once __DIR__ . '/../middleware/plan.php';

class ProviderController
{
    public function index(array $args): void
    {
        $user = $args['user'];
        $data = Provider::findByBusiness($user['business_id']);
        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public function store(array $args): void
    {
        $user = $args['user'];
        $body = $args['body'];

        if (empty(trim($body['name'] ?? ''))) {
            sendJson(400, ['success' => false, 'message' => 'El nombre es requerido']);
        }

        $plan = getBusinessPlan($user['business_id']);
        $current = Provider::countByBusiness($user['business_id']);

        $limits = ['standard' => 1, 'premium' => 2, 'salon_vip' => 5];
        $maxProviders = $limits[$plan] ?? 1;

        if ($current >= $maxProviders) {
            sendJson(403, [
                'success' => false,
                'message' => 'Has alcanzado el maximo de prestadores para tu plan (' . $maxProviders . ')',
            ]);
        }

        $userId = null;
        if (!empty($body['email'])) {
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND business_id = :bid LIMIT 1');
            $stmt->execute([':email' => $body['email'], ':bid' => $user['business_id']]);
            $row = $stmt->fetch();
            if ($row) $userId = (int)$row['id'];
        }

        $id = Provider::create([
            'business_id' => $user['business_id'],
            'user_id'     => $userId,
            'name'        => trim($body['name']),
            'bio'         => $body['bio'] ?? null,
            'photo'       => $body['photo'] ?? null,
        ]);

        $provider = Provider::findById($id, $user['business_id']);
        sendJson(201, ['success' => true, 'message' => 'Prestador creado', 'data' => $provider]);
    }

    public function show(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $provider = Provider::findById($id, $user['business_id']);
        if (!$provider) {
            sendJson(404, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        sendJson(200, ['success' => true, 'data' => $provider]);
    }

    public function update(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];

        $provider = Provider::findById($id, $user['business_id']);
        if (!$provider) {
            sendJson(404, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        Provider::update($id, $body);
        $updated = Provider::findById($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Prestador actualizado', 'data' => $updated]);
    }

    public function schedule(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $provider = Provider::findById($id, $user['business_id']);
        if (!$provider) {
            sendJson(404, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        $data = Provider::getSchedule($id);
        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public function updateSchedule(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];

        $provider = Provider::findById($id, $user['business_id']);
        if (!$provider) {
            sendJson(404, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        if (empty($body['schedules']) || !is_array($body['schedules'])) {
            sendJson(400, ['success' => false, 'message' => 'Horarios requeridos']);
        }

        Provider::setSchedule($id, $body['schedules']);
        $data = Provider::getSchedule($id);
        sendJson(200, ['success' => true, 'message' => 'Horario actualizado', 'data' => $data]);
    }

    public function block(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];

        $provider = Provider::findById($id, $user['business_id']);
        if (!$provider) {
            sendJson(404, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        if (empty($body['start_date'])) {
            sendJson(400, ['success' => false, 'message' => 'Fecha de inicio requerida']);
        }

        $blockId = Provider::addBlockedTime($id, $body);
        sendJson(201, ['success' => true, 'message' => 'Tiempo bloqueado', 'data' => ['id' => $blockId]]);
    }

    public function dashboard(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $provider = Provider::findById($id, $user['business_id']);
        if (!$provider) {
            sendJson(404, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        $metrics = Provider::getDashboardMetrics($id);
        sendJson(200, ['success' => true, 'data' => $metrics]);
    }
}
