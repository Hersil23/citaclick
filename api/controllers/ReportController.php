<?php

require_once __DIR__ . '/../config/database.php';

class ReportController
{
    public function index(array $args): void
    {
        $user = $args['user'];
        $query = $args['query'];

        $dateFrom = $query['date_from'] ?? date('Y-m-01');
        $dateTo = $query['date_to'] ?? date('Y-m-t');
        $providerId = $query['provider_id'] ?? null;
        $serviceId = $query['service_id'] ?? null;

        $db = Database::getInstance();
        $where = 'a.business_id = :bid AND a.date BETWEEN :from AND :to';
        $params = [':bid' => $user['business_id'], ':from' => $dateFrom, ':to' => $dateTo];

        if ($providerId) {
            $where .= ' AND a.provider_id = :pid';
            $params[':pid'] = $providerId;
        }

        if ($serviceId) {
            $where .= ' AND a.service_id = :sid';
            $params[':sid'] = $serviceId;
        }

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments a WHERE {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM appointments a WHERE {$where} AND a.status = 'completed'");
        $stmt->execute($params);
        $completed = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM appointments a WHERE {$where} AND a.status = 'cancelled'");
        $stmt->execute($params);
        $cancelled = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(a.price), 0) as revenue FROM appointments a WHERE {$where} AND a.status = 'completed'");
        $stmt->execute($params);
        $revenue = (float)$stmt->fetch()['revenue'];

        $attended = $total - $cancelled;
        $attendanceRate = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
        $avgTicket = $completed > 0 ? round($revenue / $completed, 2) : 0;

        $stmt = $db->prepare("
            SELECT a.date, COUNT(*) as count
            FROM appointments a
            WHERE {$where} AND a.status != 'cancelled'
            GROUP BY a.date
            ORDER BY a.date
        ");
        $stmt->execute($params);
        $byDay = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT a.status, COUNT(*) as count
            FROM appointments a
            WHERE {$where}
            GROUP BY a.status
        ");
        $stmt->execute($params);
        $byStatus = $stmt->fetchAll();

        sendJson(200, [
            'success' => true,
            'data' => [
                'total_appointments' => $total,
                'completed'          => $completed,
                'cancelled'          => $cancelled,
                'attendance_rate'    => $attendanceRate,
                'total_revenue'      => $revenue,
                'avg_ticket'         => $avgTicket,
                'by_day'             => $byDay,
                'by_status'          => $byStatus,
                'date_from'          => $dateFrom,
                'date_to'            => $dateTo,
            ],
        ]);
    }
}
