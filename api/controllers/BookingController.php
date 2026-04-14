<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../middleware/rate_limit.php';
require_once __DIR__ . '/NotificationController.php';

class BookingController
{
    public function show(array $args): void
    {
        $slug = $args['params']['slug'] ?? '';
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT b.id, b.name, b.slug, b.theme, b.description, b.logo_url, b.phone, b.whatsapp,
                   b.currency_code, b.currency_mode, b.exchange_rate
            FROM businesses b
            WHERE b.slug = :slug AND b.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([':slug' => $slug]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        $bid = (int)$business['id'];

        // Services
        $stmt = $db->prepare('
            SELECT id, name, description, duration_minutes, price_usd, category_id
            FROM services
            WHERE business_id = :bid AND is_active = 1
            ORDER BY sort_order ASC, name ASC
        ');
        $stmt->execute([':bid' => $bid]);
        $services = $stmt->fetchAll();

        // Providers
        $stmt = $db->prepare('
            SELECT id, name
            FROM providers
            WHERE business_id = :bid AND is_active = 1
            ORDER BY name
        ');
        $stmt->execute([':bid' => $bid]);
        $providers = $stmt->fetchAll();

        sendJson(200, [
            'success' => true,
            'data' => [
                'business'  => $business,
                'services'  => $services,
                'providers' => $providers,
            ],
        ]);
    }

    public function getSlots(array $args): void
    {
        $slug = $args['params']['slug'] ?? '';
        $query = $args['query'];
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM businesses WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        $bid = (int)$business['id'];
        $date = $query['date'] ?? date('Y-m-d');
        $duration = (int)($query['duration'] ?? 30);

        $providerId = $query['provider_id'] ?? null;
        if (!$providerId) {
            $stmt = $db->prepare('SELECT id FROM providers WHERE business_id = :bid AND is_active = 1 ORDER BY id LIMIT 1');
            $stmt->execute([':bid' => $bid]);
            $prov = $stmt->fetch();
            $providerId = $prov ? (int)$prov['id'] : null;
        }

        if (!$providerId) {
            sendJson(200, ['success' => true, 'data' => []]);
            return;
        }

        $slots = Appointment::getAvailableSlots((int)$providerId, $date, $duration);
        sendJson(200, ['success' => true, 'data' => $slots]);
    }

    public function findClient(array $args): void
    {
        $slug = $args['params']['slug'] ?? '';
        $query = $args['query'];
        $idNumber = $query['id_number'] ?? '';

        if (empty(trim($idNumber))) {
            sendJson(400, ['success' => false, 'message' => 'Numero de identificacion requerido']);
        }

        $db = Database::getInstance();

        // Get business
        $stmt = $db->prepare('SELECT id FROM businesses WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        // Find client by id_number
        $stmt = $db->prepare('
            SELECT id, name, phone, email, id_number
            FROM clients
            WHERE business_id = :bid AND id_number = :idn
            LIMIT 1
        ');
        $stmt->execute([':bid' => $business['id'], ':idn' => trim($idNumber)]);
        $client = $stmt->fetch();

        if ($client) {
            sendJson(200, ['success' => true, 'found' => true, 'data' => $client]);
        } else {
            sendJson(200, ['success' => true, 'found' => false, 'data' => null]);
        }
    }

    public function createAppointment(array $args): void
    {
        // Rate limit: max 10 bookings per IP per 30 min
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            $limiter = checkRateLimit('booking|' . $ip, 'booking', 10, 30);
            if ($limiter['blocked']) {
                sendJson(429, ['success' => false, 'message' => 'Demasiados intentos. Intenta en 30 minutos.']);
            }
            recordAttempt('booking|' . $ip, 'booking');
        } catch (\Exception $e) {
            // rate_limits table may not exist — skip
        }

        $slug = $args['params']['slug'] ?? '';
        $body = $args['body'];
        $db = Database::getInstance();

        // Validate business
        $stmt = $db->prepare('SELECT id FROM businesses WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        $bid = (int)$business['id'];

        // Validate required fields
        $required = ['service_id', 'date', 'start_time', 'client_name', 'client_phone', 'client_id_number'];
        foreach ($required as $field) {
            if (empty(trim($body[$field] ?? ''))) {
                sendJson(400, ['success' => false, 'message' => 'Todos los campos son requeridos']);
            }
        }

        // Validate date is not in the past
        if ($body['date'] < date('Y-m-d')) {
            sendJson(400, ['success' => false, 'message' => 'No se pueden agendar citas en fechas pasadas']);
        }

        // Find or create client
        $stmt = $db->prepare('SELECT id FROM clients WHERE business_id = :bid AND id_number = :idn LIMIT 1');
        $stmt->execute([':bid' => $bid, ':idn' => trim($body['client_id_number'])]);
        $client = $stmt->fetch();

        if ($client) {
            $clientId = (int)$client['id'];
            // Update client info
            $stmt = $db->prepare('UPDATE clients SET name = :name, phone = :phone, email = :email WHERE id = :id');
            $stmt->execute([
                ':id'    => $clientId,
                ':name'  => trim($body['client_name']),
                ':phone' => trim($body['client_phone']),
                ':email' => trim($body['client_email'] ?? ''),
            ]);
        } else {
            // Create new client
            $stmt = $db->prepare('
                INSERT INTO clients (business_id, name, id_number, phone, email, created_at)
                VALUES (:bid, :name, :idn, :phone, :email, NOW())
            ');
            $stmt->execute([
                ':bid'   => $bid,
                ':name'  => trim($body['client_name']),
                ':idn'   => trim($body['client_id_number']),
                ':phone' => trim($body['client_phone']),
                ':email' => trim($body['client_email'] ?? '') ?: null,
            ]);
            $clientId = (int)$db->lastInsertId();
        }

        // Get service
        $stmt = $db->prepare('SELECT duration_minutes, price_usd FROM services WHERE id = :id AND business_id = :bid LIMIT 1');
        $stmt->execute([':id' => $body['service_id'], ':bid' => $bid]);
        $service = $stmt->fetch();

        if (!$service) {
            sendJson(404, ['success' => false, 'message' => 'Servicio no encontrado']);
        }

        // Get provider
        $providerId = $body['provider_id'] ?? null;
        if (!$providerId) {
            $stmt = $db->prepare('SELECT id FROM providers WHERE business_id = :bid AND is_active = 1 ORDER BY id LIMIT 1');
            $stmt->execute([':bid' => $bid]);
            $prov = $stmt->fetch();
            $providerId = $prov ? (int)$prov['id'] : null;
        }

        if (!$providerId) {
            sendJson(400, ['success' => false, 'message' => 'No hay prestadores disponibles']);
        }

        $duration = (int)$service['duration_minutes'];
        $startTime = $body['start_time'];
        $endTime = date('H:i', strtotime($startTime) + ($duration * 60));

        // Check availability
        $slots = Appointment::getAvailableSlots((int)$providerId, $body['date'], $duration);
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

        $appointmentId = Appointment::create([
            'business_id' => $bid,
            'provider_id' => (int)$providerId,
            'client_id'   => $clientId,
            'service_id'  => (int)$body['service_id'],
            'date'        => $body['date'],
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'price'       => $service['price_usd'],
        ]);

        try {
            NotificationController::sendConfirmation($appointmentId, $bid);
        } catch (\Exception $e) {
            // notification failure should not block booking
        }

        sendJson(201, [
            'success' => true,
            'message' => 'Cita agendada exitosamente',
            'data' => ['appointment_id' => $appointmentId],
        ]);
    }
}
