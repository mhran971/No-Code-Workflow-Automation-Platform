<?php

namespace Modules\Integrations\Services\Gmail;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use RuntimeException;

class GmailClient
{
    private const SEND_URL  = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Send an HTML email via the Gmail API.
     *
     * @return array{id: string, threadId: string} Gmail API response
     *
     * @throws IntegrationException  When the stored connection has no refresh token.
     * @throws RuntimeException      When Gmail API returns a non-2xx response.
     */
    public function send(
        IntegrationConnection $connection,
        string $to,
        string $subject,
        string $body,
        ?string $cc = null,
        ?string $bcc = null,
        ?string $messageId = null,
    ): array {
        $token   = $this->freshAccessToken($connection);
        $raw     = $this->buildRfc2822($to, $subject, $body, $cc, $bcc, $messageId);
        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $response = Http::withToken($token)
            ->post(self::SEND_URL, ['raw' => $encoded]);

        if ($response->failed()) {
            $detail = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("Gmail API error: {$detail}");
        }

        return $response->json();
    }

    // ─── Token management ─────────────────────────────────────────────────────

    private function freshAccessToken(IntegrationConnection $connection): string
    {
        $authConfig = $connection->auth_config;

        $expiresAt = isset($authConfig['expires_at'])
            ? Carbon::parse($authConfig['expires_at'])
            : null;

        // Refresh proactively 5 minutes before expiry so we never send with a
        // token that might expire mid-flight.
        if ($expiresAt === null || $expiresAt->subMinutes(5)->isPast()) {
            return $this->refreshAccessToken($connection);
        }

        return Crypt::decryptString($authConfig['access_token']);
    }

    private function refreshAccessToken(IntegrationConnection $connection): string
    {
        $authConfig = $connection->auth_config;

        if (empty($authConfig['refresh_token'])) {
            throw new IntegrationException(
                'Google connection has no refresh token. Please reconnect Google via Integrations.',
            );
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type'    => 'refresh_token',
            'client_id'     => config('integrations.providers.google.client_id'),
            'client_secret' => config('integrations.providers.google.client_secret'),
            'refresh_token' => Crypt::decryptString($authConfig['refresh_token']),
        ]);

        if ($response->failed()) {
            throw new IntegrationException(
                'Failed to refresh Google access token. Please reconnect Google via Integrations.',
            );
        }

        $data          = $response->json();
        $newToken      = (string) $data['access_token'];
        $expiresIn     = (int) ($data['expires_in'] ?? 3600);

        // Persist the new token and updated expiry; leave refresh_token unchanged.
        $connection->update([
            'auth_config' => array_merge($authConfig, [
                'access_token' => Crypt::encryptString($newToken),
                'expires_at'   => now()->addSeconds($expiresIn)->toIso8601String(),
            ]),
        ]);

        return $newToken;
    }

    // ─── RFC 2822 builder ─────────────────────────────────────────────────────

    private function buildRfc2822(
        string $to,
        string $subject,
        string $body,
        ?string $cc,
        ?string $bcc,
        ?string $messageId,
    ): string {
        $headers   = [];
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

        if ($cc !== null) {
            $headers[] = 'Cc: ' . $cc;
        }
        if ($bcc !== null) {
            $headers[] = 'Bcc: ' . $bcc;
        }
        if ($messageId !== null) {
            $headers[] = 'Message-ID: <' . $messageId . '>';
        }

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';

        // Body is base64-encoded within the MIME message; the entire message is
        // then base64url-encoded again for the Gmail API `raw` field.
        return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
    }
}
