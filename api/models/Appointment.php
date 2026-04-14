<?php

require_once __DIR__ . '/../config/database.php';

class Appointment
{
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO appointments
            (business_id, provider_id, client_id, service_id, appointment_date, start_time, end_time,
             status, price_charged, currency, notes)
            VALUES
            (:bid, :pid, :cid, :sid, :date, :start, :end, "pending", :price, :currency, :notes)
        ');
        $stmt->execute([
            ':bid'      => $data['business_id'],
            ':pid'      => $data['provider_id'],
            ':cid'      => $data['client_id'],
            ':sid'      => $data['service_id'],
            ':date'     => $data['date'] ?? $data['appointment_date'],
            ':start'    => $data['start_time'],
            ':end'      => $data['end_time'],
            ':price'    => $data['price'] ?? $data['price_charged'] ?? 0,
            ':currency' => $data['currency'] ?? 'USD',
            ':notes'    => $data['notes'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function findById(int $id, int $businessId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*,
                   c.name AS client_name, c.phone AS client_phone, c.email AS client_email,
                   s.name AS service_name, s.duration_minutes AS service_duration,
                   p.name AS provider_name
            FROM appointments a
            LEFT JOIN clients c ON c.id = a.client_id
            LEFT JOIN services s ON s.id = a.service_id
            LEFT JOIN providers p ON p.id = a.provider_id
            WHERE a.id = :id AND a.business_id = :bid
            LIMIT 1
        ');
        $stmt->execute([':id' => $id, ':bid' => $businessId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByBusiness(int $businessId, array $filters = []): array
    {
        $db = Database::getInstance();
        $where = ['a.business_id = :bid'];
        $params = [':bid' => $businessId];

        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['provider_id'])) {
            $where[] = 'a.provider_id = :pid';
            $params[':pid'] = $filters['provider_id'];
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'a.client_id = :cid';
            $params[':cid'] = $filters['client_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'a.appointment_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'a.appointment_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(c.name LIKE :search OR c.phone LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT a.*,
                   c.name AS client_name, c.phone AS client_phone,
                   s.name AS service_name,
                   p.name AS provider_name
            FROM appointments a
            LEFT JOIN clients c ON c.id = a.client_id
            LEFT JOIN services s ON s.id = a.service_id
            LEFT JOIN providers p ON p.id = a.provider_id
            WHERE {$whereClause}
            ORDER BY a.appointment_date DESC, a.start_time ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status, ?string $cancelledBy = null, ?string $reason = null): bool
    {
        $db = Database::getInstance();

        if ($status === 'cancelled') {
            try {
                $stmt = $db->prepare('
                    UPDATE appointments
                    SET status = :status, cancelled_by = :cby, cancel_reason = :reason
                    WHERE id = :id
                ');
                return $stmt->execute([
                    ':id'     => $id,
                    ':status' => $status,
                    ':cby'    => substr((string)($cancelledBy ?? ''), 0, 50) ?: null,
                    ':reason' => $reason,
                ]);
            } catch (\PDOException $e) {
                // Fallback if cancelled_by column has restrictive type
                $stmt = $db->prepare('UPDATE appointments SET status = :status WHERE id = :id');
                return $stmt->execute([':id' => $id, ':status' => $status]);
            }
        }

        $stmt = $db->prepare('UPDATE appointments SET status = :status WHERE id = :id');
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['appointment_date', 'start_time', 'end_time', 'price_charged', 'currency', 'notes',
                     'provider_id', 'service_id', 'client_id', 'status'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        // Map legacy keys
        if (array_key_exists('date', $data) && !array_key_exists('appointment_date', $data)) {
            $fields[] = "appointment_date = :appointment_date";
            $params[":appointment_date"] = $data['date'];
        }
        if (array_key_exists('price', $data) && !array_key_exists('price_charged', $data)) {
            $fields[] = "price_charged = :price_charged";
            $params[":price_charged"] = $data['price'];
        }

        if (empty($fields)) return false;

        $fieldStr = implode(', ', $fields);
        $stmt = $db->prepare("UPDATE appointments SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM appointments WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public static function getAvailableSlots(int $providerId, string $date, int $duration = 30): array
    {
        $db = Database::getInstance();

        $dayOfWeek = date('N', strtotime($date));

        $stmt = $db->prepare('
            SELECT start_time, end_time, slot_minutes
            FROM provider_schedules
            WHERE provider_id = :pid AND day_of_week = :dow AND is_available = 1
            ORDER BY start_time
        ');
        $stmt->execute([':pid' => $providerId, ':dow' => $dayOfWeek]);
        $blocks = $stmt->fetchAll();

        if (empty($blocks)) {
            $dow = (int)$dayOfWeek;
            if ($dow >= 1 && $dow <= 6) {
                $blocks = [['start_time' => '08:00', 'end_time' => '18:00', 'slot_minutes' => 30]];
            } else {
                return [];
            }
        }

        $stmt = $db->prepare('
            SELECT id FROM provider_blocked_times
            WHERE provider_id = :pid AND blocked_date = :date
        ');
        $stmt->execute([':pid' => $providerId, ':date' => $date]);
        if ($stmt->fetch()) return [];

        $stmt = $db->prepare('
            SELECT start_time, end_time
            FROM appointments
            WHERE provider_id = :pid AND appointment_date = :date AND status NOT IN ("cancelled")
            ORDER BY start_time
        ');
        $stmt->execute([':pid' => $providerId, ':date' => $date]);
        $booked = $stmt->fetchAll();

        // If date is today, filter out past hours
        $isToday = ($date === date('Y-m-d'));
        $nowTime = $isToday ? date('H:i') : '00:00';

        $slots = [];
        foreach ($blocks as $block) {
            $slotDuration = $duration ?: (int)$block['slot_minutes'] ?: 30;
            $current = strtotime($block['start_time']);
            $end = strtotime($block['end_time']);

            while ($current + ($slotDuration * 60) <= $end) {
                $slotStart = date('H:i', $current);
                $slotEnd = date('H:i', $current + ($slotDuration * 60));

                $conflict = false;
                foreach ($booked as $b) {
                    if ($slotStart < $b['end_time'] && $slotEnd > $b['start_time']) {
                        $conflict = true;
                        break;
                    }
                }

                if (!$conflict && $slotStart >= $nowTime) {
                    $slots[] = [
                        'start_time' => $slotStart,
                        'end_time'   => $slotEnd,
                    ];
                }

                $current += $slotDuration * 60;
            }
        }

        return $slots;
    }

    public static function getMetrics(int $businessId): array
    {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM appointments WHERE business_id = :bid AND appointment_date = :today AND status != "cancelled"');
        $stmt->execute([':bid' => $businessId, ':today' => $today]);
        $todayCount = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM appointments WHERE business_id = :bid AND status = "pending"');
        $stmt->execute([':bid' => $businessId]);
        $pendingCount = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM clients WHERE business_id = :bid');
        $stmt->execute([':bid' => $businessId]);
        $clientCount = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare('SELECT COALESCE(SUM(price_charged), 0) as total FROM appointments WHERE business_id = :bid AND appointment_date BETWEEN :start AND :end AND status = "completed"');
        $stmt->execute([':bid' => $businessId, ':start' => $monthStart, ':end' => $monthEnd]);
        $revenue = (float)$stmt->fetch()['total'];

        return [
            'today'         => $todayCount,
            'pending'       => $pendingCount,
            'total_clients' => $clientCount,
            'revenue'       => $revenue,
        ];
    }
}
