<?php

class JWT
{
    private static string $algorithm = 'sha256';
    private static int $expiration = 86400; // 24 hours

    private static function getSecret(): string
    {
        $secret = getenv('JWT_SECRET');
        if ($secret === false || $secret === '') {
            $secret = 'citaclick_jwt_secret_change_in_production_2024';
        }
        return $secret;
    }

    public static function generate(array $payload): string
    {
        $header = self::base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expiration;

        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac(self::$algorithm, $header . '.' . $payloadEncoded, self::getSecret(), true)
        );

        return $header . '.' . $payloadEncoded . '.' . $signature;
    }

    public static function verify(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = self::base64UrlEncode(
            hash_hmac(self::$algorithm, $header . '.' . $payload, self::getSecret(), true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $data = self::decode($token);
        if (!$data || !isset($data['exp'])) {
            return false;
        }

        return $data['exp'] > time();
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
