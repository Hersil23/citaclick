<?php

require_once __DIR__ . '/../config/database.php';

class Provider
{
    public static function findByBusiness(int $businessId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT p.*, u.email
            FROM providers p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.business_id = :bid
            ORDER BY p.name
        ');
        $stmt->execute([':bid' => $businessId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $businessId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT p.*, u.email
            FROM providers p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.id = :id AND p.business_id = :bid
            LIMIT 1
        ');
        $stmt->execute([':id' => $id, ':bid' => $businessId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO providers (business_id, user_id, name, bio, photo, status, created_at, updated_at)
            VALUES (:bid, :uid, :name, :bio, :photo, "active", NOW(), NOW())
        ');
        $stmt->execute([
            ':bid'   => $data['business_id'],
            ':uid'   => $data['user_id'] ?? null,
            ':name'  => $data['name'],
            ':bio'   => $data['bio'] ?? null,
            ':photo' => $data['photo'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'bio', 'photo', 'status'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($fields)) return false;
        $fields[] = 'updated_at = NOW()';
        $stmt = $db->prepare('UPDATE providers SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public static function countByBusiness(int $businessId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM providers WHERE business_id = :bid AND status = "active"');
        $stmt->execute([':bid' => $businessId]);
        return (int)$stmt->fetch()['cnt'];
    }

    public static function getSchedule(int $providerId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM provider_schedules WHERE provider_id = :pid ORDER BY day_of_week');
        $stmt->execute([':pid' => $providerId]);
        return $stmt->fetchAll();
    }

    public static function setSchedule(int $providerId, array $schedules): void
    {
        $db = Database::getInstance();
        $db->prepare('DELETE FROM provider_schedules WHERE provider_id = :pid')->execute([':pid' => $providerId]);

        $stmt = $db->prepare('
            INSERT INTO provider_schedules (provider_id, day_of_week, start_time, end_time, slot_duration, is_active)
            VALUES (:pid, :dow, :start, :end, :slot, :active)
        ');

        foreach ($schedules as $s) {
            $stmt->execute([
                ':pid'    => $providerId,
                ':dow'    => $s['day_of_week'],
                ':start'  => $s['start_time'],
                ':end'    => $s['end_time'],
                ':slot'   => $s['slot_duration'] ?? 30,
                ':active' => $s['is_active'] ?? 1,
            ]);
        }
    }

    public static function addBlockedTime(int $providerId, array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO provider_blocked_times (provider_id, start_date, end_date, start_time, end_time, reason, created_at)
            VALUES (:pid, :sd, :ed, :st, :et, :reason, NOW())
        ');
        $stmt->execute([
            ':pid'    => $providerId,
            ':sd'     => $data['start_date'],
            ':ed'     => $data['end_date'] ?? $data['start_date'],
            ':st'     => $data['start_time'] ?? null,
            ':et'     => $data['end_time'] ?? null,
            ':reason' => $data['reason'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function getDashboardMetrics(int $providerId): array
    {
        $db = Database::getInstance();
        $today = date('Y-m-d');

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM appointments WHERE provider_id = :pid AND date = :today AND status != "cancelled"');
        $stmt->execute([':pid' => $providerId, ':today' => $today]);
        $todayCount = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM appointments WHERE provider_id = :pid AND status = "pending"');
        $stmt->execute([':pid' => $providerId]);
        $pending = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM appointments WHERE provider_id = :pid AND date = :today AND status = "completed"');
        $stmt->execute([':pid' => $providerId, ':today' => $today]);
        $completed = (int)$stmt->fetch()['cnt'];

        return [
            'today_appointments' => $todayCount,
            'pending'            => $pending,
            'completed_today'    => $completed,
        ];
    }
}
