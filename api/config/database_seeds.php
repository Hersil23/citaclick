<?php

/*
 * Database seeds — precios y trial actualizados
 *
 * Ejecutar una sola vez en el servidor o via phpMyAdmin:
 *
 * UPDATE plans SET price = 7,  trial_days = 21 WHERE slug = 'standard';
 * UPDATE plans SET price = 13, trial_days = 21 WHERE slug = 'premium';
 * UPDATE plans SET price = 25, trial_days = 21 WHERE slug = 'salon_vip';
 */

require_once __DIR__ . '/database.php';

try {
    $db = Database::getInstance();

    $stmt = $db->prepare("UPDATE plans SET price = :price, trial_days = :trial WHERE slug = :slug");

    $plans = [
        ['slug' => 'standard',  'price' => 7,  'trial' => 21],
        ['slug' => 'premium',   'price' => 13, 'trial' => 21],
        ['slug' => 'salon_vip', 'price' => 25, 'trial' => 21],
    ];

    foreach ($plans as $plan) {
        $stmt->execute([
            ':price' => $plan['price'],
            ':trial' => $plan['trial'],
            ':slug'  => $plan['slug'],
        ]);
    }

    echo "Plans updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
