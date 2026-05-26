<?php

namespace Tests\Feature;

use App\Jobs\SendTicketTrackingWhatsAppJob;
use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\User;
use App\Services\TicketTrackingService;
use App\Services\WhatsAppCloudClient;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TicketPublicTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->technician = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        $customer = Customer::create(['name' => 'Ahmed Ali', 'phone' => '01001234567']);
        $brand = Brand::create(['name' => 'Yamaha', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'R1',
            'year' => 2023,
        ]);
        $bike = CustomerBike::create([
            'customer_id' => $customer->id,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN-TRACK-1',
        ]);

        $this->ticket = Ticket::create([
            'user_id' => $this->technician->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'customer_notes' => 'Please call before pickup.',
            'total' => 150,
            'public_token' => (string) Str::uuid(),
        ]);

        $task = TicketTask::create([
            'ticket_id' => $this->ticket->id,
            'name' => 'Oil change',
            'status' => 'completed',
            'subtotal' => 150,
        ]);

        $sector = MaintenanceServiceSector::create(['name' => 'Fluids']);
        $service = MaintenanceService::create([
            'name' => 'Engine oil change',
            'currency_pricing' => 'EGP',
            'service_price' => 150,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'maintenance_service_sector_id' => $sector->id,
        ]);

        TicketItem::create([
            'ticket_id' => $this->ticket->id,
            'task_id' => $task->id,
            'maintenance_service_id' => $service->id,
            'price_snapshot' => 150,
            'discount' => 0,
            'qty' => 1,
            'subtotal' => 150,
        ]);
    }

    public function test_meta_returns_preview_without_sensitive_data(): void
    {
        config([
            'shop.logo_url' => '/logo.ico',
            'shop.tracking_auto_refresh_minutes' => 3,
        ]);

        $this->getJson("/api/public/tickets/{$this->ticket->public_token}/meta")
            ->assertOk()
            ->assertJsonPath('ticket.status', 'in_progress')
            ->assertJsonPath('ticket.status_label', 'In progress')
            ->assertJsonPath('shop.name', 'Real Performance Garage')
            ->assertJsonPath('shop.logo_url', '/logo.ico')
            ->assertJsonPath('shop.auto_refresh_minutes', 3)
            ->assertJsonStructure(['progress' => ['timeline']])
            ->assertJsonMissingPath('ticket.total');
    }

    public function test_verify_with_correct_phone_returns_session_and_ticket(): void
    {
        $response = $this->postJson("/api/public/tickets/{$this->ticket->public_token}/verify", [
            'phone' => '01001234567',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['tracking_session', 'ticket'])
            ->assertJsonPath('ticket.ticket.total', 150)
            ->assertJsonPath('ticket.ticket.status_label', 'In progress')
            ->assertJsonPath('ticket.progress.tasks_percent', 100)
            ->assertJsonPath('ticket.customer.name', 'Ahmed Ali')
            ->assertJsonPath('ticket.tasks.0.items.0.label', 'Engine oil change');
    }

    public function test_verify_with_wrong_phone_returns_generic_error(): void
    {
        $this->postJson("/api/public/tickets/{$this->ticket->public_token}/verify", [
            'phone' => '01009999999',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid link or phone.');
    }

    public function test_show_requires_valid_session(): void
    {
        $this->getJson("/api/public/tickets/{$this->ticket->public_token}")
            ->assertUnauthorized();
    }

    public function test_show_with_session_returns_ticket(): void
    {
        $session = app(TicketTrackingService::class)->createSession($this->ticket);

        $this->getJson("/api/public/tickets/{$this->ticket->public_token}", [
            TicketTrackingService::SESSION_HEADER => $session,
        ])
            ->assertOk()
            ->assertJsonPath('ticket.ticket.customer_notes', 'Please call before pickup.');
    }

    public function test_soft_deleted_ticket_returns_not_found(): void
    {
        $token = $this->ticket->public_token;
        $this->ticket->delete();

        $this->getJson("/api/public/tickets/{$token}/meta")
            ->assertNotFound();
    }

    public function test_ensure_tracking_link_creates_token_without_sending_whatsapp(): void
    {
        $this->ticket->update(['public_token' => null]);

        config(['services.frontend.public_url' => 'https://example.com']);

        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/ensure-tracking-link")
            ->assertOk()
            ->assertJsonStructure(['public_token', 'tracking_url']);

        $this->ticket->refresh();
        $this->assertNotNull($this->ticket->public_token);
        $this->assertNull($this->ticket->tracking_link_sent_at);
    }

    public function test_send_tracking_link_dispatches_whatsapp_and_records_sent_at(): void
    {
        $mock = Mockery::mock(WhatsAppCloudClient::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->once()
            ->andReturn(['messages' => [['id' => 'wamid.test']]]);
        $this->app->instance(WhatsAppCloudClient::class, $mock);

        config([
            'services.whatsapp.phone_number_id' => '123',
            'services.whatsapp.access_token' => 'token',
            'services.frontend.public_url' => 'https://example.com',
        ]);

        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/send-tracking-link")
            ->assertOk()
            ->assertJsonStructure(['sent_at', 'tracking_url', 'public_token']);

        $this->ticket->refresh();
        $this->assertNotNull($this->ticket->tracking_link_sent_at);
        $this->assertSame(1, $this->ticket->tracking_link_send_count);
    }

    public function test_regenerate_tracking_token_invalidates_old_token(): void
    {
        $oldToken = $this->ticket->public_token;

        config(['services.frontend.public_url' => 'https://example.com']);

        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/regenerate-tracking-token")
            ->assertOk()
            ->assertJsonStructure(['public_token', 'tracking_url']);

        $this->ticket->refresh();
        $this->assertNotSame($oldToken, $this->ticket->public_token);

        $this->getJson("/api/public/tickets/{$oldToken}/meta")
            ->assertNotFound();
    }

    public function test_send_tracking_link_fails_without_customer_phone(): void
    {
        $this->ticket->customer?->update(['phone' => '']);

        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/send-tracking-link")
            ->assertStatus(422);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
