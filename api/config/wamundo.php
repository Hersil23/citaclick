<?php

class Wamundo
{
    private static int $maxRetries = 3;

    private static function getApiKey(): string
    {
        $key = getenv('WAMUNDO_API_KEY');
        return $key !== false ? $key : '';
    }

    private static function getBaseUrl(): string
    {
        $url = getenv('WAMUNDO_API_URL');
        return $url !== false ? $url : 'https://api.wamundo.com/v1';
    }

    public static function send(string $phone, string $template, array $variables = []): array
    {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Wamundo API key not configured'];
        }

        $payload = json_encode([
            'phone'     => $phone,
            'template'  => $template,
            'variables' => $variables,
        ]);

        $attempt = 0;
        $lastError = '';

        while ($attempt < self::$maxRetries) {
            $attempt++;

            $ch = curl_init(self::getBaseUrl() . '/messages/send');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $lastError = $curlError;
                continue;
            }

            $data = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'data' => $data];
            }

            $lastError = $data['message'] ?? 'HTTP ' . $httpCode;

            if ($httpCode >= 400 && $httpCode < 500) {
                break;
            }
        }

        return ['success' => false, 'error' => $lastError];
    }
}
