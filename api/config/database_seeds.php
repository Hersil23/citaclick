<?php

/*
 * Database seeds — precios y trial actualizados
 *
 * Ejecutar en phpMyAdmin:
 *
 * UPDATE plans SET price_monthly=7.00, trial_days=21, max_providers=1 WHERE name='Standard';
 * UPDATE plans SET price_monthly=13.00, trial_days=21, max_providers=2 WHERE name='Premium';
 * UPDATE plans SET price_monthly=25.00, trial_days=21, max_providers=5 WHERE name='Salon VIP';
 */

require_once __DIR__ . '/database.php';

try {
    $db = Database::getInstance();

    $stmt = $db->prepare("UPDATE plans SET price_monthly = :price, trial_days = :trial, max_providers = :max WHERE name = :name");

    $plans = [
        ['name' => 'Standard',  'price' => 7.00,  'trial' => 21, 'max' => 1],
        ['name' => 'Premium',   'price' => 13.00, 'trial' => 21, 'max' => 2],
        ['name' => 'Salon VIP', 'price' => 25.00, 'trial' => 21, 'max' => 5],
    ];

    foreach ($plans as $plan) {
        $stmt->execute([
            ':price' => $plan['price'],
            ':trial' => $plan['trial'],
            ':max'   => $plan['max'],
            ':name'  => $plan['name'],
        ]);
    }

    echo "Plans updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
