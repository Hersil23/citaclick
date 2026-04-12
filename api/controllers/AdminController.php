<?php

require_once __DIR__ . '/../config/database.php';

class AdminController
{
    public function stats(array $args): void
    {
        $db = Database::getInstance();

        $stmt = $db->query('SELECT COUNT(*) as cnt FROM businesses');
        $totalBusinesses = (int)$stmt->fetch()['cnt'];

        $stmt = $db->query("SELECT COUNT(*) as cnt FROM businesses WHERE status = 'active'");
        $activeBusinesses = (int)$stmt->fetch()['cnt'];

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT s.business_id) as cnt
            FROM subscriptions s
            WHERE s.status = 'active' AND s.end_date >= CURDATE()
              AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $trialBusinesses = (int)$stmt->fetch()['cnt'];

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(p.price), 0) as revenue
            FROM subscriptions s
            JOIN plans p ON p.id = s.plan_id
            WHERE s.status = 'active' AND s.start_date <= :end AND (s.end_date IS NULL OR s.end_date >= :start)
        ");
        $stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
        $monthlyRevenue = (float)$stmt->fetch()['revenue'];

        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM businesses
            WHERE created_at >= :start
        ");
        $stmt->execute([':start' => $monthStart]);
        $newSignups = (int)$stmt->fetch()['cnt'];

        sendJson(200, [
            'success' => true,
            'data' => [
                'total_businesses'  => $totalBusinesses,
                'active_businesses' => $activeBusinesses,
                'trial_businesses'  => $trialBusinesses,
                'monthly_revenue'   => $monthlyRevenue,
                'new_signups'       => $newSignups,
            ],
        ]);
    }

    public function businesses(array $args): void
    {
        $query = $args['query'];
        $db = Database::getInstance();

        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(1, (int)($query['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = '1=1';
        $params = [];

        if (!empty($query['status'])) {
            $where .= ' AND b.status = :status';
            $params[':status'] = $query['status'];
        }

        if (!empty($query['search'])) {
            $where .= ' AND (b.name LIKE :search OR b.slug LIKE :search2)';
            $params[':search'] = '%' . $query['search'] . '%';
            $params[':search2'] = '%' . $query['search'] . '%';
        }

        $stmt = $db->prepare("
            SELECT b.*, p.name AS plan_name, p.slug AS plan_slug,
                   s.status AS sub_status, s.end_date
            FROM businesses b
            LEFT JOIN subscriptions s ON s.business_id = b.id AND s.status = 'active'
            LEFT JOIN plans p ON p.id = s.plan_id
            WHERE {$where}
            ORDER BY b.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public function updateBusiness(array $args): void
    {
        $id = (int)$args['params']['id'];
        $body = $args['body'];
        $db = Database::getInstance();

        if (!empty($body['plan_slug'])) {
            $stmt = $db->prepare('SELECT id FROM plans WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $body['plan_slug']]);
            $plan = $stmt->fetch();

            if ($plan) {
                $db->prepare("UPDATE subscriptions SET plan_id = :pid, updated_at = NOW() WHERE business_id = :bid AND status = 'active'")
                   ->execute([':pid' => $plan['id'], ':bid' => $id]);
            }
        }

        if (!empty($body['status'])) {
            $db->prepare('UPDATE businesses SET status = :status, updated_at = NOW() WHERE id = :id')
               ->execute([':status' => $body['status'], ':id' => $id]);
        }

        sendJson(200, ['success' => true, 'message' => 'Negocio actualizado']);
    }

    public function suspend(array $args): void
    {
        $id = (int)$args['params']['id'];
        $db = Database::getInstance();
        $db->prepare("UPDATE businesses SET status = 'suspended', updated_at = NOW() WHERE id = :id")
           ->execute([':id' => $id]);

        sendJson(200, ['success' => true, 'message' => 'Negocio suspendido']);
    }

    public function activate(array $args): void
    {
        $id = (int)$args['params']['id'];
        $db = Database::getInstance();
        $db->prepare("UPDATE businesses SET status = 'active', updated_at = NOW() WHERE id = :id")
           ->execute([':id' => $id]);

        sendJson(200, ['success' => true, 'message' => 'Negocio activado']);
    }
}
