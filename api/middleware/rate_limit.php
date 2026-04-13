<?php

require_once __DIR__ . '/../config/database.php';

function checkRateLimit(string $identifier, string $action, int $maxAttempts = 10, int $windowMinutes = 30): array
{
    $db = Database::getInstance();

    // Clean up expired entries
    $db->prepare('DELETE FROM rate_limits WHERE expires_at < NOW()')->execute();

    // Count recent attempts in the sliding window
    $stmt = $db->prepare('
        SELECT COUNT(*) as attempts, MIN(created_at) as first_attempt
        FROM rate_limits
        WHERE identifier = :id AND action = :action AND created_at > DATE_SUB(NOW(), INTERVAL :window MINUTE)
    ');
    $stmt->execute([':id' => $identifier, ':action' => $action, ':window' => $windowMinutes]);
    $row = $stmt->fetch();
    $attempts = (int)$row['attempts'];

    // Hybrid banking-style: calculate delay based on attempts
    $delay = 0;
    $blocked = false;

    if ($attempts >= $maxAttempts) {
        // Hard block after max attempts
        $blocked = true;
    } elseif ($attempts >= 8) {
        $delay = 30;   // 30 seconds
    } elseif ($attempts >= 6) {
        $delay = 10;   // 10 seconds
    } elseif ($attempts >= 4) {
        $delay = 2;    // 2 seconds
    }

    // Check if last attempt was too recent (enforce delay)
    if ($delay > 0 && !$blocked) {
        $stmt = $db->prepare('
            SELECT created_at FROM rate_limits
            WHERE identifier = :id AND action = :action
            ORDER BY created_at DESC LIMIT 1
        ');
        $stmt->execute([':id' => $identifier, ':action' => $action]);
        $last = $stmt->fetch();

        if ($last) {
            $elapsed = time() - strtotime($last['created_at']);
            if ($elapsed < $delay) {
                // Apply artificial delay — sleep the remaining time
                sleep($delay - $elapsed);
            }
        }
    }

    return [
        'blocked' => $blocked,
        'attempts' => $attempts,
        'delay' => $delay,
    ];
}

function recordAttempt(string $identifier, string $action, int $windowMinutes = 30): void
{
    $db = Database::getInstance();
    $stmt = $db->prepare('
        INSERT INTO rate_limits (identifier, action, created_at, expires_at)
        VALUES (:id, :action, NOW(), DATE_ADD(NOW(), INTERVAL :window MINUTE))
    ');
    $stmt->execute([':id' => $identifier, ':action' => $action, ':window' => $windowMinutes]);
}

function clearAttempts(string $identifier, string $action): void
{
    $db = Database::getInstance();
    $stmt = $db->prepare('DELETE FROM rate_limits WHERE identifier = :id AND action = :action');
    $stmt->execute([':id' => $identifier, ':action' => $action]);
}
