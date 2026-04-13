<?php

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/database.php';

function authenticateRequest(): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($header)) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    if (empty($header) || !str_starts_with($header, 'Bearer ')) {
        sendJson(401, ['success' => false, 'message' => 'Token de autenticacion requerido']);
        return null;
    }

    $token = substr($header, 7);

    if (!JWT::verify($token)) {
        sendJson(401, ['success' => false, 'message' => 'Token invalido o expirado']);
        return null;
    }

    // Check if token was blacklisted (logout)
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT id FROM token_blacklist WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1');
    $stmt->execute([':hash' => hash('sha256', $token)]);
    if ($stmt->fetch()) {
        sendJson(401, ['success' => false, 'message' => 'Sesion cerrada']);
        return null;
    }

    $payload = JWT::decode($token);
    if (!$payload || !isset($payload['user_id'])) {
        sendJson(401, ['success' => false, 'message' => 'Token malformado']);
        return null;
    }

    return $payload;
}

function requireRole(array $user, array $allowedRoles): bool
{
    if (!isset($user['role']) || !in_array($user['role'], $allowedRoles, true)) {
        sendJson(403, ['success' => false, 'message' => 'No tienes permisos para esta accion']);
        return false;
    }
    return true;
}
