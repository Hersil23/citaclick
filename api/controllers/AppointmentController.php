<?php

require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/NotificationController.php';

class AppointmentController
{
    public function index(array $args): void
    {
        $user = $args['user'];
        $query = $args['query'];

        if (!empty($query['metrics'])) {
            $providerId = null;
            if ($user['role'] === 'provider') {
                $providerId = $this->getProviderId($user['user_id']);
            }
            try {
                $metrics = Appointment::getMetrics($user['business_id'], $providerId);
            } catch (\PDOException $e) {
                $metrics = ['today' => 0, 'pending' => 0, 'total_clients' => 0, 'revenue' => 0];
            }
            sendJson(200, ['success' => true, 'data' => $metrics]);
        }

        $filters = [
            'status'      => $query['status'] ?? null,
            'provider_id' => $query['provider_id'] ?? null,
            'client_id'   => $query['client_id'] ?? null,
            'date_from'   => $query['date_from'] ?? null,
            'date_to'     => $query['date_to'] ?? null,
            'search'      => $query['search'] ?? null,
            'page'        => $query['page'] ?? 1,
            'limit'       => $query['limit'] ?? 50,
        ];

        if ($user['role'] === 'provider') {
            $filters['provider_id'] = $this->getProviderId($user['user_id']);
        }

        try {
            $data = Appointment::findByBusiness($user['business_id'], $filters);
        } catch (\PDOException $e) {
            $data = [];
        }

        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public function store(array $args): void
    {
        $user = $args['user'];
        $body = $args['body'];

        $required = ['client_id', 'service_id', 'date', 'start_time'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                sendJson(400, [
                    'success' => false,
                    'message' => 'Campos requeridos: cliente, servicio, fecha y hora',
                    'errors' => [$field => 'Campo requerido'],
                ]);
            }
        }

        if ($body['date'] < date('Y-m-d')) {
            sendJson(400, ['success' => false, 'message' => 'No se pueden agendar citas en fechas pasadas']);
        }

        $providerId = $body['provider_id'] ?? $this->getProviderId($user['user_id']);
        if (!$providerId) {
            sendJson(400, ['success' => false, 'message' => 'Prestador no encontrado']);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT duration_minutes, price_usd FROM services WHERE id = :id AND business_id = :bid LIMIT 1');
        $stmt->execute([':id' => $body['service_id'], ':bid' => $user['business_id']]);
        $service = $stmt->fetch();

        if (!$service) {
            sendJson(404, ['success' => false, 'message' => 'Servicio no encontrado']);
        }

        $duration = (int)$service['duration_minutes'];
        $startTime = $body['start_time'];
        $endTime = date('H:i', strtotime($startTime) + ($duration * 60));

        $slots = Appointment::getAvailableSlots($providerId, $body['date'], $duration);
        $available = false;
        foreach ($slots as $slot) {
            if ($slot['start_time'] === $startTime) {
                $available = true;
                break;
            }
        }

        if (!$available) {
            sendJson(409, ['success' => false, 'message' => 'El horario seleccionado no esta disponible']);
        }

        $id = Appointment::create([
            'business_id' => $user['business_id'],
            'provider_id' => $providerId,
            'client_id'   => $body['client_id'],
            'service_id'  => $body['service_id'],
            'date'        => $body['date'],
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'duration'    => $duration,
            'price'       => $body['price'] ?? $service['price_usd'],
            'notes'       => $body['notes'] ?? null,
        ]);

        $appointment = Appointment::findById($id, $user['business_id']);

        try {
            NotificationController::sendConfirmation($id, $user['business_id']);
        } catch (\Exception $e) {
            // notification failure should not block appointment creation
        }

        sendJson(201, [
            'success' => true,
            'message' => 'Cita creada exitosamente',
            'data' => $appointment,
        ]);
    }

    public function show(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $appointment = Appointment::findById($id, $user['business_id']);
        if (!$appointment) {
            sendJson(404, ['success' => false, 'message' => 'Cita no encontrada']);
        }

        sendJson(200, ['success' => true, 'data' => $appointment]);
    }

    public function update(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];

        $existing = Appointment::findById($id, $user['business_id']);
        if (!$existing) {
            sendJson(404, ['success' => false, 'message' => 'Cita no encontrada']);
        }

        if (!empty($body['status'])) {
            $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'];
            if (!in_array($body['status'], $validStatuses, true)) {
                sendJson(400, ['success' => false, 'message' => 'Estado no valido']);
            }

            if ($body['status'] === 'cancelled') {
                Appointment::updateStatus(
                    $id,
                    'cancelled',
                    $user['role'],
                    $body['cancel_reason'] ?? null
                );
                try { NotificationController::sendCancellation($id, $user['business_id']); } catch (\Exception $e) {}
            } else {
                Appointment::updateStatus($id, $body['status']);
            }
        }

        $updateFields = [];
        foreach (['date', 'start_time', 'end_time', 'duration', 'price', 'notes', 'provider_id', 'service_id'] as $f) {
            if (array_key_exists($f, $body)) {
                $updateFields[$f] = $body[$f];
            }
        }

        if (!empty($updateFields)) {
            Appointment::update($id, $updateFields);
            if (isset($updateFields['date']) || isset($updateFields['start_time'])) {
                try { NotificationController::sendReschedule($id, $user['business_id']); } catch (\Exception $e) {}
            }
        }

        $updated = Appointment::findById($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Cita actualizada', 'data' => $updated]);
    }

    public function destroy(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $existing = Appointment::findById($id, $user['business_id']);
        if (!$existing) {
            sendJson(404, ['success' => false, 'message' => 'Cita no encontrada']);
        }

        Appointment::delete($id);
        sendJson(200, ['success' => true, 'message' => 'Cita eliminada']);
    }

    public function availableSlots(array $args): void
    {
        $user = $args['user'];
        $query = $args['query'];

        $providerId = $query['provider_id'] ?? $this->getProviderId($user['user_id']);
        $date = $query['date'] ?? date('Y-m-d');
        $duration = (int)($query['duration'] ?? 30);

        if (!$providerId) {
            sendJson(400, ['success' => false, 'message' => 'Prestador requerido']);
        }

        $slots = Appointment::getAvailableSlots((int)$providerId, $date, $duration);

        sendJson(200, ['success' => true, 'data' => $slots]);
    }

    private function getProviderId(int $userId): ?int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM providers WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
