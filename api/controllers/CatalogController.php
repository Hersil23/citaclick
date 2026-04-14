<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../controllers/NotificationController.php';

class CatalogController
{
    public function show(array $args): void
    {
        $slug = $args['params']['slug'] ?? '';

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*, p.name AS plan_name, p.has_catalog, p.has_qr, p.has_google_maps
            FROM businesses b
            LEFT JOIN subscriptions s ON s.business_id = b.id AND s.status IN ("active", "trial")
            LEFT JOIN plans p ON p.id = s.plan_id
            WHERE b.slug = :slug AND b.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([':slug' => $slug]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        $hasCatalog = !empty($business['has_catalog']);

        $stmt = $db->prepare('
            SELECT s.*, sc.name AS category_name
            FROM services s
            LEFT JOIN service_categories sc ON sc.id = s.category_id
            WHERE s.business_id = :bid AND s.is_active = 1
            ORDER BY sc.name ASC, s.name ASC
        ');
        $stmt->execute([':bid' => $business['id']]);
        $services = $stmt->fetchAll();

        $providers = [];
        if ($hasCatalog) {
            $stmt = $db->prepare('
                SELECT id, name, bio, avatar_url
                FROM providers
                WHERE business_id = :bid AND is_active = 1
                ORDER BY name
            ');
            $stmt->execute([':bid' => $business['id']]);
            $providers = $stmt->fetchAll();
        }

        $categories = [];
        foreach ($services as $svc) {
            $catName = $svc['category_name'] ?? 'General';
            if (!isset($categories[$catName])) {
                $categories[$catName] = [];
            }
            $categories[$catName][] = $svc;
        }

        sendJson(200, [
            'success' => true,
            'data' => [
                'business' => [
                    'name'          => $business['name'],
                    'slug'          => $business['slug'],
                    'description'   => $business['description'] ?? '',
                    'logo'          => $business['logo_url'] ?? null,
                    'theme'         => $business['theme'],
                    'address'       => $business['address'] ?? '',
                    'phone'         => $business['phone'] ?? '',
                    'instagram'     => $business['instagram'] ?? '',
                    'facebook'      => $business['facebook'] ?? '',
                    'whatsapp'      => $business['whatsapp'] ?? '',
                    'google_maps'   => $business['google_maps_url'] ?? '',
                    'currency_code' => $business['currency_code'] ?? 'USD',
                    'currency_mode' => $business['currency_mode'] ?? 'usd',
                    'exchange_rate' => (float)($business['exchange_rate'] ?? 1),
                ],
                'services'    => $services,
                'categories'  => $categories,
                'providers'   => $providers,
                'can_book'    => $hasCatalog,
                'has_qr'      => !empty($business['has_qr']),
                'has_maps'    => !empty($business['has_google_maps']),
            ],
        ]);
    }

    public function book(array $args): void
    {
        $slug = $args['params']['slug'] ?? '';
        $body = $args['body'];

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM businesses WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $business = $stmt->fetch();

        if (!$business) {
            sendJson(404, ['success' => false, 'message' => 'Negocio no encontrado']);
        }

        $businessId = (int)$business['id'];

        $required = ['client_name', 'client_phone', 'service_id', 'date', 'start_time'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                sendJson(400, [
                    'success' => false,
                    'message' => 'Todos los campos son requeridos',
                    'errors' => [$field => 'Campo requerido'],
                ]);
            }
        }

        $clientId = Client::findOrCreateByPhone(
            $businessId,
            $body['client_name'],
            $body['client_phone'],
            $body['client_email'] ?? null
        );

        $stmt = $db->prepare('SELECT duration_minutes, price_usd FROM services WHERE id = :id AND business_id = :bid LIMIT 1');
        $stmt->execute([':id' => $body['service_id'], ':bid' => $businessId]);
        $service = $stmt->fetch();

        if (!$service) {
            sendJson(404, ['success' => false, 'message' => 'Servicio no encontrado']);
        }

        $providerId = $body['provider_id'] ?? null;
        if (!$providerId) {
            $stmt = $db->prepare('SELECT id FROM providers WHERE business_id = :bid AND is_active = 1 ORDER BY id LIMIT 1');
            $stmt->execute([':bid' => $businessId]);
            $prov = $stmt->fetch();
            $providerId = $prov ? (int)$prov['id'] : null;
        }

        if (!$providerId) {
            sendJson(400, ['success' => false, 'message' => 'No hay prestadores disponibles']);
        }

        $duration = (int)$service['duration_minutes'];
        $startTime = $body['start_time'];
        $endTime = date('H:i', strtotime($startTime) + ($duration * 60));

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
            'business_id' => $businessId,
            'provider_id' => (int)$providerId,
            'client_id'   => $clientId,
            'service_id'  => (int)$body['service_id'],
            'date'        => $body['date'],
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'duration'    => $duration,
            'price'       => $service['price_usd'],
            'notes'       => $body['notes'] ?? null,
        ]);

        NotificationController::sendConfirmation($appointmentId, $businessId);

        sendJson(201, [
            'success' => true,
            'message' => 'Cita agendada exitosamente',
            'data' => ['appointment_id' => $appointmentId],
        ]);
    }
}
