<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** FCM HTTP v1. Prayer reminders are local to the device and do not pass through here. */
class FcmService
{
    public function isConfigured(): bool
    {
        $path = config('deenmate.fcm.credentials_path');

        return filled(config('deenmate.fcm.project_id')) && filled($path) && is_readable($path);
    }

    public function sendToTopic(string $topic, string $title, string $body): void
    {
        $project = config('deenmate.fcm.project_id');
        Http::withToken($this->accessToken())
            ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                'message' => ['topic' => $topic, 'notification' => ['title' => $title, 'body' => $body]],
            ])
            ->throw();
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', 3000, function () {
            $credentials = json_decode(file_get_contents(config('deenmate.fcm.credentials_path')), true, flags: JSON_THROW_ON_ERROR);
            $now = time();
            $encode = fn (array $v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM token request.');
            }

            return Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
            ])->throw()->json('access_token');
        });
    }
}
