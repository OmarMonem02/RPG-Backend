<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudClient
{
    /**
     * @param  list<array<string, mixed>>  $components  Meta template components (header/body with named or positional parameters)
     */
    public function sendTemplateMessage(string $toPhone, string $templateName, string $languageCode, array $components = []): array
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');
        $apiVersion = config('services.whatsapp.api_version', 'v21.0');

        if (! filled($phoneNumberId) || ! filled($accessToken)) {
            $missing = array_filter([
                ! filled($phoneNumberId) ? 'WHATSAPP_PHONE_NUMBER_ID' : null,
                ! filled($accessToken) ? 'WHATSAPP_ACCESS_TOKEN' : null,
            ]);
            throw new RuntimeException(
                'WhatsApp API is not configured. Set in .env: '.implode(', ', $missing).'.'
            );
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            $hint = match (true) {
                str_contains((string) $message, '132001') => " Template \"{$templateName}\" with language \"{$languageCode}\" is not approved in Meta. Run: php artisan whatsapp:list-templates",
                str_contains((string) $message, '132000') => ' Template parameter count or placement does not match Meta (check header vs body variables). Run: php artisan whatsapp:list-templates',
                default => '',
            };
            throw new RuntimeException("WhatsApp API error: {$message}{$hint}");
        }

        return $response->json() ?? [];
    }
}
