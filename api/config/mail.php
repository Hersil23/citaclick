<?php

class Mailer
{
    private static function getConfig(): array
    {
        return [
            'host'     => getenv('SMTP_HOST') ?: 'localhost',
            'port'     => (int)(getenv('SMTP_PORT') ?: 587),
            'username' => getenv('SMTP_USER') ?: '',
            'password' => getenv('SMTP_PASS') ?: '',
            'from'     => getenv('SMTP_FROM') ?: 'noreply@citaclick.net',
            'fromName' => 'CitaClick',
        ];
    }

    public static function send(string $to, string $subject, string $templateName, array $variables = []): array
    {
        $config = self::getConfig();

        $templatePath = __DIR__ . '/../templates/mail/' . $templateName . '.html';

        if (file_exists($templatePath)) {
            $body = file_get_contents($templatePath);
            foreach ($variables as $key => $value) {
                $body = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $body);
            }
        } else {
            $body = self::buildPlainTemplate($subject, $variables);
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['fromName'] . ' <' . $config['from'] . '>',
            'Reply-To: ' . $config['from'],
            'X-Mailer: CitaClick',
        ];

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

        return [
            'success' => $sent,
            'error'   => $sent ? null : 'Failed to send email',
        ];
    }

    private static function buildPlainTemplate(string $subject, array $vars): string
    {
        $lines = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;">';
        $lines .= '<h2 style="color:#E94560;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>';

        foreach ($vars as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', $key));
            $lines .= '<p><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong> '
                     . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $lines .= '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">';
        $lines .= '<p style="color:#888;font-size:12px;">CitaClick — citaclick.net</p>';
        $lines .= '</div>';

        return $lines;
    }
}
