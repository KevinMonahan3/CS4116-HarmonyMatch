<?php

class ResendMailer {
    private string $apiKey;
    private string $from;
    private string $replyTo;
    private int $timeout;
    public string $lastError = '';

    public function __construct() {
        $config = require __DIR__ . '/../config/resend.php';
        $this->apiKey = (string)($config['api_key'] ?? '');
        $this->from = (string)($config['from'] ?? 'HarmonyMatch <onboarding@resend.dev>');
        $this->replyTo = (string)($config['reply_to'] ?? '');
        $this->timeout = max(1, (int)($config['timeout'] ?? 10));
    }

    public function isConfigured(): bool {
        return $this->apiKey !== '';
    }

    public function sendPasswordReset(string $toEmail, string $resetUrl): bool {
        $html = '<p>Hello,</p>'
            . '<p>Use the button below to reset your HarmonyMatch password. This link expires in 1 hour.</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700;">'
            . 'Reset password</a></p>'
            . '<p>If the button does not work, copy and paste this link into your browser:</p>'
            . '<p style="word-break:break-all;">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>If you did not request this, you can ignore this email.</p>';

        return $this->send($toEmail, 'Reset your HarmonyMatch password', $html);
    }

    private function send(string $toEmail, string $subject, string $html): bool {
        if (!$this->isConfigured()) {
            $this->lastError = 'Resend API key is not configured';
            return false;
        }

        if (!function_exists('curl_init')) {
            $this->lastError = 'PHP curl extension is not installed';
            return false;
        }

        $payload = [
            'from' => $this->from,
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $html,
        ];
        if ($this->replyTo !== '') {
            $payload['reply_to'] = $this->replyTo;
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            $this->lastError = $curlError !== ''
                ? $curlError
                : 'Resend returned HTTP ' . $status . ': ' . (string)$response;
            error_log('Resend email failed: ' . $this->lastError);
            return false;
        }

        return true;
    }
}
