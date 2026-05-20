<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WhatsAppListTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:list-templates {--name= : Filter by template name}';

    protected $description = 'List approved WhatsApp message templates from Meta (use exact name + language in .env)';

    public function handle(): int
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');
        $apiVersion = config('services.whatsapp.api_version', 'v21.0');

        if (! filled($phoneNumberId) || ! filled($accessToken)) {
            $this->error('Set WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN in .env first.');

            return self::FAILURE;
        }

        $wabaId = config('services.whatsapp.business_account_id');

        if (! filled($wabaId)) {
            $wabaResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}", [
                    'fields' => 'whatsapp_business_account',
                ]);

            if (! $wabaResponse->successful()) {
                $this->error('Could not resolve WhatsApp Business Account ID.');
                $this->line($wabaResponse->json('error.message') ?? $wabaResponse->body());
                $this->line('Optional: set WHATSAPP_BUSINESS_ACCOUNT_ID in .env (from Meta API setup).');

                return self::FAILURE;
            }

            $wabaId = $wabaResponse->json('whatsapp_business_account.id');
        }

        if (! filled($wabaId)) {
            $this->error('WhatsApp Business Account ID not found.');

            return self::FAILURE;
        }

        $this->info("WhatsApp Business Account: {$wabaId}");
        $this->newLine();

        $query = ['limit' => 100];
        if ($name = $this->option('name')) {
            $query['name'] = $name;
        }

        $response = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", $query);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            $this->error($message);

            if (str_contains((string) $message, 'message_templates')) {
                $this->warnWrongBusinessAccountId($accessToken, $apiVersion, (string) $wabaId, $phoneNumberId);
            }

            return self::FAILURE;
        }

        $templates = $response->json('data') ?? [];

        if ($templates === []) {
            $this->warn('No templates found. Create one in Meta Business Manager → WhatsApp → Message templates.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($templates as $template) {
            $language = $template['language'] ?? ($template['languages'][0] ?? '—');
            $rows[] = [
                $template['name'] ?? '—',
                $language,
                $template['status'] ?? '—',
                $template['category'] ?? '—',
            ];
        }

        $this->table(['Name (WHATSAPP_TRACKING_TEMPLATE_NAME)', 'Language (WHATSAPP_TEMPLATE_LANGUAGE)', 'Status', 'Category'], $rows);

        $this->newLine();
        $this->line('Copy the exact <name> and <language> into .env, then run: php artisan config:clear');
        $this->line('Configured now: '.config('services.whatsapp.tracking_template_name').' / '.config('services.whatsapp.template_language'));

        return self::SUCCESS;
    }

    private function warnWrongBusinessAccountId(string $accessToken, string $apiVersion, string $configuredId, string $phoneNumberId): void
    {
        $node = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/{$apiVersion}/{$configuredId}", ['fields' => 'id,name'])
            ->json();

        $this->newLine();
        $this->warn('WHATSAPP_BUSINESS_ACCOUNT_ID does not look like a WhatsApp Business Account.');

        if (isset($node['name']) && ! isset($node['error'])) {
            $this->line("Graph API returned a profile named \"{$node['name']}\" for ID {$configuredId} — that is usually a Facebook User ID, not a WABA.");
        }

        $this->line('Use the WhatsApp Business Account ID from Meta:');
        $this->line('  developers.facebook.com → your app → WhatsApp → API setup → WhatsApp Business Account ID');
        $this->line('It is a numeric ID, different from Phone number ID and from your personal Facebook user ID.');
        $this->line('Remove or fix WHATSAPP_BUSINESS_ACCOUNT_ID in .env, then run: php artisan config:clear');

        $wabaFromPhone = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}", [
                'fields' => 'whatsapp_business_account{id}',
            ])
            ->json('whatsapp_business_account.id');

        if (filled($wabaFromPhone) && $wabaFromPhone !== $configuredId) {
            $this->newLine();
            $this->info("Your phone number resolves to WABA: {$wabaFromPhone}");
            $this->line("Set WHATSAPP_BUSINESS_ACCOUNT_ID={$wabaFromPhone}");
        }
    }
}
