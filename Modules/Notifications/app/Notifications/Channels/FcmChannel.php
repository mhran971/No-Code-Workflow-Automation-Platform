<?php

namespace Modules\Notifications\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Models\DeviceToken;

/**
 * Custom FCM v1 HTTP channel.
 *
 * Uses Laravel's HTTP client to call the FCM v1 API directly,
 * avoiding heavy SDK dependencies that require PHP 8.3+.
 *
 * Each notification class must implement a `toFcm($notifiable): array`
 * method returning ['title' => ..., 'body' => ..., 'data' => [...]].
 */
class FcmChannel
{
    /**
     * Send the notification to all registered devices.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = $notifiable->routeNotificationForFcm();

        if (empty($tokens)) {
            return;
        }

        $fcm = $notification->toFcm($notifiable);
        $projectId = config('services.firebase.project_id');
        $accessToken = $this->getAccessToken();

        if (! $accessToken || ! $projectId) {
            Log::warning('FCM: Missing access token or project ID. Skipping push notification.');

            return;
        }

        foreach ($tokens as $token) {
            $this->sendToToken($projectId, $accessToken, $token, $fcm);
        }
    }

    /**
     * Send a single FCM message to one device token.
     */
    private function sendToToken(string $projectId, string $accessToken, string $token, array $fcm): void
    {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $fcm['title'] ?? '',
                    'body' => $fcm['body'] ?? '',
                ],
                'data' => array_map('strval', $fcm['data'] ?? []),
            ],
        ];

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        if ($response->failed()) {
            $error = $response->json('error.details.0.errorCode', $response->json('error.status', 'UNKNOWN'));

            // Remove invalid / unregistered tokens automatically.
            if (in_array($error, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                DeviceToken::where('token', $token)->delete();
                Log::info("FCM: Removed stale token {$token}");
            } else {
                Log::warning("FCM: Failed to send to {$token}", ['error' => $response->json()]);
            }
        }
    }

    /**
     * Obtain a short-lived OAuth2 access token from the service account credentials.
     *
     * Uses the JWT grant flow (urn:ietf:params:oauth:grant-type:jwt-bearer).
     */
    private function getAccessToken(): ?string
    {
        $credentialsPath = config('services.firebase.credentials');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            Log::warning('FCM: Service account credentials file not found at: '.$credentialsPath);

            return null;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $now = time();
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimSet = $this->base64url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signatureInput = "{$header}.{$claimSet}";
        openssl_sign($signatureInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = "{$signatureInput}.".$this->base64url($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            Log::error('FCM: Failed to obtain access token.', ['response' => $response->json()]);

            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Base64url-encode (no padding, URL-safe).
     */
    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
