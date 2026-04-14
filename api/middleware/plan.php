<?php

require_once __DIR__ . '/../config/database.php';

function checkPlanFeature(int $businessId, string $feature): bool
{
    $premiumFeatures = [
        'catalog',
        'public_link',
        'qr_code',
        'assistant',
        'google_maps',
        'currency_conversion',
        'public_profile',
    ];

    $salonVipFeatures = [
        'multi_provider',
        'role_management',
        'provider_dashboards',
        'provider_schedules',
        'global_admin_panel',
    ];

    $db = Database::getInstance();

    $stmt = $db->prepare('
        SELECT p.name
        FROM subscriptions s
        JOIN plans p ON p.id = s.plan_id
        WHERE s.business_id = :bid
          AND s.status IN ("active", "trial")
          AND (s.ends_at IS NULL OR s.ends_at >= NOW())
        ORDER BY s.created_at DESC
        LIMIT 1
    ');
    $stmt->execute([':bid' => $businessId]);
    $row = $stmt->fetch();

    if (!$row) {
        sendJson(403, [
            'success' => false,
            'message' => 'No tienes una suscripcion activa',
        ]);
        return false;
    }

    $plan = $row['name'];

    if (in_array($feature, $salonVipFeatures, true) && $plan !== 'Salon VIP') {
        sendJson(403, [
            'success' => false,
            'message' => 'Esta funcionalidad requiere el plan Salon VIP',
            'data' => ['required_plan' => 'Salon VIP'],
        ]);
        return false;
    }

    if (in_array($feature, $premiumFeatures, true) && $plan === 'Standard') {
        sendJson(403, [
            'success' => false,
            'message' => 'Esta funcionalidad requiere el plan Premium o superior',
            'data' => ['required_plan' => 'Premium'],
        ]);
        return false;
    }

    return true;
}

function getBusinessPlan(int $businessId): ?string
{
    $db = Database::getInstance();

    $stmt = $db->prepare('
        SELECT p.name
        FROM subscriptions s
        JOIN plans p ON p.id = s.plan_id
        WHERE s.business_id = :bid
          AND s.status IN ("active", "trial")
          AND (s.ends_at IS NULL OR s.ends_at >= NOW())
        ORDER BY s.created_at DESC
        LIMIT 1
    ');
    $stmt->execute([':bid' => $businessId]);
    $row = $stmt->fetch();

    return $row ? $row['name'] : null;
}
