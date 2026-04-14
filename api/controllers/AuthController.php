<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../middleware/rate_limit.php';

class AuthController
{
    public function login(array $args): void
    {
        $body = $args['body'];
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            sendJson(400, [
                'success' => false,
                'message' => 'Email y contrasena son requeridos',
            ]);
        }

        // Rate limit by email + IP (hybrid banking-style)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = checkRateLimit($email . '|' . $ip, 'login', 20);
        if ($limiter['blocked']) {
            sendJson(429, [
                'success' => false,
                'message' => 'Demasiados intentos. Intenta en 30 minutos.',
            ]);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT u.*, b.id AS business_id, b.name AS business_name, b.slug, b.theme
            FROM users u
            LEFT JOIN businesses b ON b.id = u.business_id
            WHERE u.email = :email
            LIMIT 1
        ');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Get plan and provider separately
        if ($user) {
            try {
                $ps = $db->prepare('SELECT p.name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.business_id = :bid AND s.status IN ("active","trial") ORDER BY s.created_at DESC LIMIT 1');
                $ps->execute([':bid' => $user['business_id']]);
                $pr = $ps->fetch();
                $user['plan_name'] = $pr ? $pr['name'] : null;
            } catch (\PDOException $e) { $user['plan_name'] = null; }

            try {
                $pp = $db->prepare('SELECT id FROM providers WHERE user_id = :uid LIMIT 1');
                $pp->execute([':uid' => $user['id']]);
                $prov = $pp->fetch();
                $user['provider_id'] = $prov ? (int)$prov['id'] : null;
            } catch (\PDOException $e) { $user['provider_id'] = null; }
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            recordAttempt($email . '|' . $ip, 'login');
            sendJson(401, [
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ]);
        }

        // Login successful — clear rate limit history
        clearAttempts($email . '|' . $ip, 'login');

        if (!($user['is_active'] ?? true)) {
            sendJson(403, [
                'success' => false,
                'message' => 'Tu cuenta ha sido suspendida',
            ]);
        }

        $token = JWT::generate([
            'user_id'     => (int)$user['id'],
            'business_id' => (int)$user['business_id'],
            'role'        => $user['role'],
            'email'       => $user['email'],
        ]);

        sendJson(200, [
            'success' => true,
            'message' => 'Inicio de sesion exitoso',
            'data' => [
                'token' => $token,
                'user' => [
                    'id'            => (int)$user['id'],
                    'name'          => $user['name'],
                    'email'         => $user['email'],
                    'role'          => $user['role'],
                    'photo'         => $user['avatar_url'] ?? null,
                    'business_id'   => (int)$user['business_id'],
                    'business_name' => $user['business_name'],
                    'slug'          => $user['slug'],
                    'theme'         => $user['theme'],
                    'plan'          => $user['plan_name'] ?? 'Standard',
                    'provider_id'   => $user['provider_id'] ? (int)$user['provider_id'] : null,
                ],
            ],
        ]);
    }

    public function register(array $args): void
    {
        $body = $args['body'];

        $required = ['business_name', 'email', 'password', 'slug', 'theme', 'plan', 'business_type'];
        foreach ($required as $field) {
            if (empty(trim($body[$field] ?? ''))) {
                sendJson(400, [
                    'success' => false,
                    'message' => 'Todos los campos son requeridos',
                    'errors' => [$field => 'Campo requerido'],
                ]);
            }
        }

        $email = trim($body['email']);
        $password = $body['password'];
        $businessName = trim($body['business_name']);
        $slug = trim($body['slug']);
        $theme = $body['theme'];
        $planSlug = $body['plan'];
        $businessType = $body['business_type'];

        if (strlen($password) < 8) {
            sendJson(400, [
                'success' => false,
                'message' => 'La contrasena debe tener al menos 8 caracteres',
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(400, [
                'success' => false,
                'message' => 'Email no valido',
            ]);
        }

        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            sendJson(409, [
                'success' => false,
                'message' => 'No se pudo completar el registro. Verifica tus datos.',
            ]);
        }

        $slug = $this->generateUniqueSlug($db, $slug);

        $stmt = $db->prepare('SELECT id FROM plans WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $planSlug]);
        $plan = $stmt->fetch();

        if (!$plan) {
            sendJson(400, [
                'success' => false,
                'message' => 'Plan no valido',
            ]);
        }

        $db->beginTransaction();

        try {
            $stmt = $db->prepare('
                INSERT INTO businesses (name, slug, theme, business_type, email, created_at)
                VALUES (:name, :slug, :theme, :btype, :email, NOW())
            ');
            $stmt->execute([
                ':name'  => $businessName,
                ':slug'  => $slug,
                ':theme' => $theme,
                ':btype' => $businessType,
                ':email' => $email,
            ]);
            $businessId = (int)$db->lastInsertId();

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $db->prepare('
                INSERT INTO users (business_id, name, email, password_hash, role, created_at)
                VALUES (:bid, :name, :email, :password_hash, "owner", NOW())
            ');
            $stmt->execute([
                ':bid'      => $businessId,
                ':name'     => $businessName,
                ':email'    => $email,
                ':password_hash' => $hashedPassword,
            ]);
            $userId = (int)$db->lastInsertId();

            $trialEnd = date('Y-m-d H:i:s', strtotime('+21 days'));
            $stmt = $db->prepare('
                INSERT INTO subscriptions (business_id, plan_id, status, start_date, starts_at, ends_at, trial_ends_at, created_at)
                VALUES (:bid, :pid, "trial", CURDATE(), NOW(), :ends_at, :ends_at, NOW())
            ');
            $trialEnd = date('Y-m-d H:i:s', strtotime('+21 days'));
            $stmt->execute([
                ':bid'      => $businessId,
                ':pid'      => $plan['id'],
                ':ends_at'  => $trialEnd,
            ]);

            $stmt = $db->prepare('
                INSERT INTO providers (business_id, user_id, name, is_active, created_at)
                VALUES (:bid, :uid, :name, 1, NOW())
            ');
            $stmt->execute([
                ':bid'  => $businessId,
                ':uid'  => $userId,
                ':name' => $businessName,
            ]);
            $providerId = (int)$db->lastInsertId();

            // Create default schedule Mon-Sat 8:00-18:00
            $scheduleStmt = $db->prepare('
                INSERT INTO provider_schedules (provider_id, day_of_week, start_time, end_time, slot_minutes, is_available)
                VALUES (:pid, :dow, :start, :end, 30, 1)
            ');
            for ($dow = 1; $dow <= 6; $dow++) {
                $scheduleStmt->execute([
                    ':pid'   => $providerId,
                    ':dow'   => $dow,
                    ':start' => '08:00',
                    ':end'   => '18:00',
                ]);
            }

            $db->commit();

            $token = JWT::generate([
                'user_id'     => $userId,
                'business_id' => $businessId,
                'role'        => 'owner',
                'email'       => $email,
            ]);

            sendJson(201, [
                'success' => true,
                'message' => 'Registro exitoso',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id'            => $userId,
                        'name'          => $businessName,
                        'email'         => $email,
                        'role'          => 'owner',
                        'photo'         => null,
                        'business_type' => $businessType,
                        'business_id'   => $businessId,
                        'business_name' => $businessName,
                        'slug'          => $slug,
                        'theme'         => $theme,
                        'plan'          => $planSlug,
                        'provider_id'   => $providerId,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            sendJson(500, [
                'success' => false,
                'message' => 'Error al crear la cuenta. Intenta nuevamente.',
                'debug' => $e->getMessage(),
            ]);
        }
    }

    public function google(array $args): void
    {
        $body = $args['body'];
        $code = $body['code'] ?? '';

        if (empty($code)) {
            sendJson(400, ['success' => false, 'message' => 'Codigo de autorizacion requerido']);
        }

        sendJson(501, [
            'success' => false,
            'message' => 'Google OAuth pendiente de configuracion del client ID',
        ]);
    }

    public function phone(array $args): void
    {
        $body = $args['body'];
        $phone = trim($body['phone'] ?? '');

        if (empty($phone)) {
            sendJson(400, ['success' => false, 'message' => 'Numero de telefono requerido']);
        }

        // Rate limit OTP requests per phone
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = checkRateLimit($phone . '|' . $ip, 'otp_request', 5, 15);
        if ($limiter['blocked']) {
            sendJson(429, [
                'success' => false,
                'message' => 'Demasiados intentos. Intenta en 15 minutos.',
            ]);
        }
        recordAttempt($phone . '|' . $ip, 'otp_request', 15);

        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO otp_codes (phone, code, expires_at, created_at)
            VALUES (:phone, :code, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
            ON DUPLICATE KEY UPDATE code = :code2, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        ');
        $stmt->execute([
            ':phone' => $phone,
            ':code'  => $otp,
            ':code2' => $otp,
        ]);

        sendJson(200, [
            'success' => true,
            'message' => 'Codigo enviado por WhatsApp',
        ]);
    }

    public function phoneVerify(array $args): void
    {
        $body = $args['body'];
        $phone = trim($body['phone'] ?? '');
        $code = trim($body['code'] ?? '');

        if (empty($phone) || empty($code)) {
            sendJson(400, ['success' => false, 'message' => 'Telefono y codigo son requeridos']);
        }

        // Rate limit OTP verification (prevent brute force on 6-digit code)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $limiter = checkRateLimit($phone . '|' . $ip, 'otp_verify', 5, 10);
        if ($limiter['blocked']) {
            sendJson(429, [
                'success' => false,
                'message' => 'Demasiados intentos. Intenta en 10 minutos.',
            ]);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM otp_codes
            WHERE phone = :phone AND code = :code AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([':phone' => $phone, ':code' => $code]);
        $otp = $stmt->fetch();

        if (!$otp) {
            recordAttempt($phone . '|' . $ip, 'otp_verify', 10);
            sendJson(401, ['success' => false, 'message' => 'Codigo invalido o expirado']);
        }

        // OTP verified — clear rate limit
        clearAttempts($phone . '|' . $ip, 'otp_verify');

        $stmt = $db->prepare('DELETE FROM otp_codes WHERE phone = :phone');
        $stmt->execute([':phone' => $phone]);

        $stmt = $db->prepare('
            SELECT u.*, b.id AS business_id, b.name AS business_name, b.slug, b.theme
            FROM users u
            LEFT JOIN businesses b ON b.id = u.business_id
            WHERE u.phone = :phone
            LIMIT 1
        ');
        $stmt->execute([':phone' => $phone]);
        $user = $stmt->fetch();

        if (!$user) {
            sendJson(404, [
                'success' => false,
                'message' => 'No hay cuenta asociada a este numero',
            ]);
        }

        $token = JWT::generate([
            'user_id'     => (int)$user['id'],
            'business_id' => (int)$user['business_id'],
            'role'        => $user['role'],
            'email'       => $user['email'],
        ]);

        sendJson(200, [
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id'            => (int)$user['id'],
                    'name'          => $user['name'],
                    'email'         => $user['email'],
                    'role'          => $user['role'],
                    'photo'         => $user['avatar_url'] ?? null,
                    'business_id'   => (int)$user['business_id'],
                    'business_name' => $user['business_name'],
                    'slug'          => $user['slug'],
                    'theme'         => $user['theme'],
                    'plan'          => $user['plan_name'] ?? 'Standard',
                ],
            ],
        ]);
    }

    public function refresh(array $args): void
    {
        $user = $args['user'];

        $token = JWT::generate([
            'user_id'     => $user['user_id'],
            'business_id' => $user['business_id'],
            'role'        => $user['role'],
            'email'       => $user['email'],
        ]);

        sendJson(200, [
            'success' => true,
            'data' => ['token' => $token],
        ]);
    }

    public function logout(array $args): void
    {
        // Blacklist the current token until it expires
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($header) && str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
            $payload = JWT::decode($token);
            if ($payload && isset($payload['exp'])) {
                $db = Database::getInstance();
                $stmt = $db->prepare('
                    INSERT INTO token_blacklist (token_hash, expires_at, created_at)
                    VALUES (:hash, :exp, NOW())
                ');
                $stmt->execute([
                    ':hash' => hash('sha256', $token),
                    ':exp'  => date('Y-m-d H:i:s', $payload['exp']),
                ]);
            }
        }

        sendJson(200, [
            'success' => true,
            'message' => 'Sesion cerrada',
        ]);
    }

    private function generateUniqueSlug(PDO $db, string $baseSlug): string
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($baseSlug));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));

        if (empty($slug)) {
            $slug = 'negocio';
        }

        $stmt = $db->prepare('SELECT id FROM businesses WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $counter = 2;
        while (true) {
            $candidate = $slug . '-' . $counter;
            $stmt->execute([':slug' => $candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $counter++;
        }
    }
}
