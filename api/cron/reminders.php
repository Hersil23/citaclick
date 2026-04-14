<?php

/**
 * Cron: Recordatorios automaticos de citas
 * Ejecutar cada hora: php /path/to/api/cron/reminders.php
 *
 * Envia recordatorios 24h y 1h antes de la cita.
 * Evita duplicados verificando notifications_log.
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/NotificationController.php';

$db = Database::getInstance();
$now = new DateTime();

// --- Recordatorio 24h ---
$from24 = (clone $now)->modify('+23 hours')->format('Y-m-d H:i:s');
$to24   = (clone $now)->modify('+25 hours')->format('Y-m-d H:i:s');

$stmt = $db->prepare("
    SELECT a.id, a.business_id
    FROM appointments a
    WHERE a.status IN ('pending', 'confirmed')
      AND CONCAT(a.appointment_date, ' ', a.start_time) BETWEEN :from_dt AND :to_dt
      AND a.id NOT IN (
          SELECT appointment_id FROM notifications_log
          WHERE type = 'reminder_24h' AND status = 'sent'
      )
");
$stmt->execute([':from_dt' => $from24, ':to_dt' => $to24]);
$reminders24 = $stmt->fetchAll();

foreach ($reminders24 as $r) {
    try {
        NotificationController::sendReminder((int)$r['id'], (int)$r['business_id'], '24h');
        echo "24h reminder sent for appointment #{$r['id']}\n";
    } catch (Exception $e) {
        echo "24h reminder FAILED for #{$r['id']}: {$e->getMessage()}\n";
    }
}

// --- Recordatorio 1h ---
$from1 = (clone $now)->modify('+50 minutes')->format('Y-m-d H:i:s');
$to1   = (clone $now)->modify('+70 minutes')->format('Y-m-d H:i:s');

$stmt = $db->prepare("
    SELECT a.id, a.business_id
    FROM appointments a
    WHERE a.status IN ('pending', 'confirmed')
      AND CONCAT(a.appointment_date, ' ', a.start_time) BETWEEN :from_dt AND :to_dt
      AND a.id NOT IN (
          SELECT appointment_id FROM notifications_log
          WHERE type = 'reminder_1h' AND status = 'sent'
      )
");
$stmt->execute([':from_dt' => $from1, ':to_dt' => $to1]);
$reminders1 = $stmt->fetchAll();

foreach ($reminders1 as $r) {
    try {
        NotificationController::sendReminder((int)$r['id'], (int)$r['business_id'], '1h');
        echo "1h reminder sent for appointment #{$r['id']}\n";
    } catch (Exception $e) {
        echo "1h reminder FAILED for #{$r['id']}: {$e->getMessage()}\n";
    }
}

echo "Done. 24h: " . count($reminders24) . ", 1h: " . count($reminders1) . "\n";
