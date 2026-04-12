<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/wamundo.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../models/Appointment.php';

class NotificationController
{
    public function index(array $args): void
    {
        $user = $args['user'];
        $query = $args['query'];

        $db = Database::getInstance();
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT * FROM notifications_log
            WHERE business_id = :bid
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute([':bid' => $user['business_id']]);
        $data = $stmt->fetchAll();

        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public static function sendConfirmation(int $appointmentId, int $businessId): void
    {
        $appt = self::getAppointmentData($appointmentId, $businessId);
        if (!$appt) return;

        $vars = self::buildVars($appt);

        self::sendWhatsApp($appt['client_phone'], 'appointment_confirmation', $vars, $businessId, $appointmentId, 'confirmation');
        self::sendEmail($appt['client_email'], 'Cita confirmada — ' . $appt['business_name'], 'confirmation', $vars, $businessId, $appointmentId, 'confirmation');
    }

    public static function sendReminder(int $appointmentId, int $businessId, string $type): void
    {
        $appt = self::getAppointmentData($appointmentId, $businessId);
        if (!$appt) return;

        $vars = self::buildVars($appt);
        $template = $type === '24h' ? 'reminder_24h' : 'reminder_1h';
        $subject = $type === '24h' ? 'Recordatorio: cita manana' : 'Recordatorio: cita en 1 hora';

        self::sendWhatsApp($appt['client_phone'], $template, $vars, $businessId, $appointmentId, 'reminder_' . $type);
        self::sendEmail($appt['client_email'], $subject . ' — ' . $appt['business_name'], 'reminder', $vars, $businessId, $appointmentId, 'reminder_' . $type);
    }

    public static function sendCancellation(int $appointmentId, int $businessId): void
    {
        $appt = self::getAppointmentData($appointmentId, $businessId);
        if (!$appt) return;

        $vars = self::buildVars($appt);

        self::sendWhatsApp($appt['client_phone'], 'appointment_cancelled', $vars, $businessId, $appointmentId, 'cancellation');
        self::sendEmail($appt['client_email'], 'Cita cancelada — ' . $appt['business_name'], 'cancellation', $vars, $businessId, $appointmentId, 'cancellation');
    }

    public static function sendReschedule(int $appointmentId, int $businessId): void
    {
        $appt = self::getAppointmentData($appointmentId, $businessId);
        if (!$appt) return;

        $vars = self::buildVars($appt);

        self::sendWhatsApp($appt['client_phone'], 'appointment_rescheduled', $vars, $businessId, $appointmentId, 'reschedule');
        self::sendEmail($appt['client_email'], 'Cita reprogramada — ' . $appt['business_name'], 'reschedule', $vars, $businessId, $appointmentId, 'reschedule');
    }

    private static function getAppointmentData(int $appointmentId, int $businessId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*, c.name AS client_name, c.phone AS client_phone, c.email AS client_email,
                   s.name AS service_name, p.name AS provider_name, b.name AS business_name
            FROM appointments a
            JOIN clients c ON c.id = a.client_id
            LEFT JOIN services s ON s.id = a.service_id
            LEFT JOIN providers p ON p.id = a.provider_id
            JOIN businesses b ON b.id = a.business_id
            WHERE a.id = :id AND a.business_id = :bid
            LIMIT 1
        ');
        $stmt->execute([':id' => $appointmentId, ':bid' => $businessId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function buildVars(array $appt): array
    {
        return [
            'client_name'   => $appt['client_name'],
            'business_name' => $appt['business_name'],
            'service_name'  => $appt['service_name'] ?? '',
            'provider_name' => $appt['provider_name'] ?? '',
            'date'          => $appt['date'],
            'start_time'    => $appt['start_time'],
            'duration'      => $appt['duration'] . ' min',
        ];
    }

    private static function sendWhatsApp(?string $phone, string $template, array $vars, int $businessId, int $appointmentId, string $type): void
    {
        if (empty($phone)) {
            self::logNotification($businessId, $appointmentId, 'whatsapp', $type, 'failed', 'No phone number');
            return;
        }

        $result = Wamundo::send($phone, $template, $vars);
        $status = $result['success'] ? 'sent' : 'failed';
        self::logNotification($businessId, $appointmentId, 'whatsapp', $type, $status, $result['error'] ?? null);
    }

    private static function sendEmail(?string $email, string $subject, string $templateName, array $vars, int $businessId, int $appointmentId, string $type): void
    {
        if (empty($email)) {
            self::logNotification($businessId, $appointmentId, 'email', $type, 'failed', 'No email address');
            return;
        }

        $result = Mailer::send($email, $subject, $templateName, $vars);
        $status = $result['success'] ? 'sent' : 'failed';
        self::logNotification($businessId, $appointmentId, 'email', $type, $status, $result['error'] ?? null);
    }

    private static function logNotification(int $businessId, int $appointmentId, string $channel, string $type, string $status, ?string $error): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO notifications_log (business_id, appointment_id, channel, type, status, error_message, created_at)
            VALUES (:bid, :aid, :channel, :type, :status, :error, NOW())
        ');
        $stmt->execute([
            ':bid'     => $businessId,
            ':aid'     => $appointmentId,
            ':channel' => $channel,
            ':type'    => $type,
            ':status'  => $status,
            ':error'   => $error,
        ]);
    }
}
