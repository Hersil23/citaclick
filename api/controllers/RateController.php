<?php

class RateController
{
    public function dollar(array $args): void
    {
        $ch = curl_init('https://ve.dolarapi.com/v1/dolares');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            sendJson(502, ['success' => false, 'message' => 'No se pudo obtener la tasa']);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            sendJson(502, ['success' => false, 'message' => 'Respuesta invalida']);
        }

        $rates = [];
        foreach ($data as $d) {
            if (($d['fuente'] ?? '') === 'oficial') {
                $rates['bcv'] = (float)($d['promedio'] ?? 0);
            }
            if (($d['fuente'] ?? '') === 'paralelo') {
                $rates['paralelo'] = (float)($d['promedio'] ?? 0);
            }
        }

        sendJson(200, ['success' => true, 'data' => $rates]);
    }
}
