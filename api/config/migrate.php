<?php

/**
 * Auto-migration: creates missing tables on first request.
 * Safe to run multiple times (uses IF NOT EXISTS).
 */

function ensureTables(): void
{
    try {
        $db = Database::getInstance();
    } catch (\Exception $e) {
        return;
    }

    // Quick check: if all core tables exist, skip migration
    $coreTables = ['clients', 'services', 'appointments', 'token_blacklist', 'rate_limits'];
    $allExist = true;
    foreach ($coreTables as $t) {
        try {
            $db->query("SELECT 1 FROM `{$t}` LIMIT 1");
        } catch (\PDOException $e) {
            $allExist = false;
            break;
        }
    }
    if ($allExist) return;

    $tables = [
        'service_categories' => "
            CREATE TABLE IF NOT EXISTS service_categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id INT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_business (business_id),
                FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'services' => "
            CREATE TABLE IF NOT EXISTS services (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id INT UNSIGNED NOT NULL,
                category_id INT UNSIGNED NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                duration INT NOT NULL DEFAULT 30,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                image VARCHAR(500) NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_business (business_id),
                FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'clients' => "
            CREATE TABLE IF NOT EXISTS clients (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(30) NULL,
                email VARCHAR(255) NULL,
                photo VARCHAR(500) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_business (business_id),
                INDEX idx_phone (business_id, phone),
                FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'appointments' => "
            CREATE TABLE IF NOT EXISTS appointments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id INT UNSIGNED NOT NULL,
                provider_id INT UNSIGNED NOT NULL,
                client_id INT UNSIGNED NOT NULL,
                service_id INT UNSIGNED NULL,
                date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                duration INT NOT NULL DEFAULT 30,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                status ENUM('pending','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
                cancelled_by VARCHAR(50) NULL,
                cancel_reason TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_business_date (business_id, date),
                INDEX idx_provider_date (provider_id, date),
                INDEX idx_client (client_id),
                INDEX idx_status (status),
                FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
                FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'provider_schedules' => "
            CREATE TABLE IF NOT EXISTS provider_schedules (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider_id INT UNSIGNED NOT NULL,
                day_of_week TINYINT NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                slot_duration INT NOT NULL DEFAULT 30,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                INDEX idx_provider_day (provider_id, day_of_week),
                FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'provider_blocked_times' => "
            CREATE TABLE IF NOT EXISTS provider_blocked_times (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider_id INT UNSIGNED NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_provider_dates (provider_id, start_date, end_date),
                FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'token_blacklist' => "
            CREATE TABLE IF NOT EXISTS token_blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX idx_token_hash (token_hash),
                INDEX idx_token_cleanup (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        'rate_limits' => "
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                INDEX idx_rate_lookup (identifier, action, created_at),
                INDEX idx_rate_cleanup (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        'reviews' => "
            CREATE TABLE IF NOT EXISTS reviews (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id INT UNSIGNED NOT NULL,
                client_id INT UNSIGNED NOT NULL,
                business_id INT UNSIGNED NOT NULL,
                rating TINYINT NOT NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_business (business_id),
                FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'notifications_log' => "
            CREATE TABLE IF NOT EXISTS notifications_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id INT UNSIGNED NOT NULL,
                appointment_id INT UNSIGNED NULL,
                channel ENUM('whatsapp','email','push') NOT NULL,
                type VARCHAR(50) NOT NULL,
                status ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_business (business_id),
                FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
    ];

    foreach ($tables as $name => $sql) {
        try {
            $db->exec($sql);
        } catch (\PDOException $e) {
            error_log("migrate: {$name} - " . $e->getMessage());
        }
    }

    // Column additions (safe to run multiple times)
    $alterations = [
        "ALTER TABLE clients ADD COLUMN id_number VARCHAR(50) NULL AFTER name",
    ];
    foreach ($alterations as $alt) {
        try {
            $db->exec($alt);
        } catch (\PDOException $e) {
            // Column likely already exists
        }
    }
}
