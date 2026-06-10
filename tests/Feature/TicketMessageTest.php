<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketTrackingService;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private User $noAccessUser;

    private Ticket $ticket;

    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->technician = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        $this->noAccessUser = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => [],
            ]),
        ]);

        $customer = Customer::create(['name' => 'Ahmed Ali', 'phone' => '01001234567']);
        $brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'R1',
            'year' => 2023,
        ]);
        $bike = CustomerBike::create([
            'customer_id' => $customer->id,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN-CHAT-1',
        ]);

        $this->ticket = Ticket::create([
            'user_id' => $this->technician->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 0,
            'public_token' => (string) Str::uuid(),
        ]);

        $this->sessionId = app(TicketTrackingService::class)->createSession($this->ticket);
    }

    public function test_staff_can_list_and_send_messages(): void
    {
        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'sender_type' => TicketMessage::SENDER_CUSTOMER,
            'body' => 'When will it be ready?',
        ]);

        $this->actingAs($this->technician)
            ->getJson("/api/tickets/{$this->ticket->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sender_type', 'customer')
            ->assertJsonPath('data.0.body', 'When will it be ready?');

        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/messages", [
                'body' => 'Tomorrow afternoon.',
            ])
            ->assertCreated()
            ->assertJsonPath('sender_type', 'staff')
            ->assertJsonPath('body', 'Tomorrow afternoon.')
            ->assertJsonPath('user.id', $this->technician->id)
            ->assertJsonPath('user.name', $this->technician->name);
    }

    public function test_staff_without_permission_cannot_access_messages(): void
    {
        $this->actingAs($this->noAccessUser)
            ->getJson("/api/tickets/{$this->ticket->id}/messages")
            ->assertForbidden();

        $this->actingAs($this->noAccessUser)
            ->postJson("/api/tickets/{$this->ticket->id}/messages", [
                'body' => 'Hello',
            ])
            ->assertForbidden();
    }

    public function test_staff_can_send_when_ticket_closed(): void
    {
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/messages", [
                'body' => 'Pickup is ready.',
            ])
            ->assertCreated()
            ->assertJsonPath('body', 'Pickup is ready.');
    }

    public function test_customer_can_list_and_send_with_session(): void
    {
        $this->postJson("/api/public/tickets/{$this->ticket->public_token}/messages", [
            'body' => 'Any update?',
        ], [
            TicketTrackingService::SESSION_HEADER => $this->sessionId,
        ])
            ->assertCreated()
            ->assertJsonPath('sender_type', 'customer');

        $this->getJson("/api/public/tickets/{$this->ticket->public_token}/messages", [
            TicketTrackingService::SESSION_HEADER => $this->sessionId,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Any update?');
    }

    public function test_customer_cannot_send_when_ticket_closed(): void
    {
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);

        $this->postJson("/api/public/tickets/{$this->ticket->public_token}/messages", [
            'body' => 'One more question',
        ], [
            TicketTrackingService::SESSION_HEADER => $this->sessionId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This ticket is closed. You can view messages but cannot send new ones.');
    }

    public function test_customer_can_still_read_when_ticket_closed(): void
    {
        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'sender_type' => TicketMessage::SENDER_STAFF,
            'user_id' => $this->technician->id,
            'body' => 'All done.',
        ]);

        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);

        $this->getJson("/api/public/tickets/{$this->ticket->public_token}/messages", [
            TicketTrackingService::SESSION_HEADER => $this->sessionId,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_public_messages_require_session(): void
    {
        $this->getJson("/api/public/tickets/{$this->ticket->public_token}/messages")
            ->assertUnauthorized();

        $this->postJson("/api/public/tickets/{$this->ticket->public_token}/messages", [
            'body' => 'Hello',
        ])->assertUnauthorized();
    }

    public function test_message_body_is_required(): void
    {
        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_staff_can_send_image_message(): void
    {
        $this->actingAs($this->technician)
            ->postJson("/api/tickets/{$this->ticket->id}/messages", [
                'image_url' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg',
                'image_public_id' => 'rpg-system/ticket-chat/sample',
                'body' => 'See attached photo',
            ])
            ->assertCreated()
            ->assertJsonPath('image_url', 'https://res.cloudinary.com/demo/image/upload/sample.jpg')
            ->assertJsonPath('body', 'See attached photo');

        $this->actingAs($this->technician)
            ->getJson("/api/tickets/{$this->ticket->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.image_url', 'https://res.cloudinary.com/demo/image/upload/sample.jpg');
    }

    public function test_customer_can_send_image_only_message(): void
    {
        $this->postJson("/api/public/tickets/{$this->ticket->public_token}/messages", [
            'image_url' => 'https://res.cloudinary.com/demo/image/upload/bike.jpg',
            'image_public_id' => 'rpg-system/ticket-chat/bike',
        ], [
            TicketTrackingService::SESSION_HEADER => $this->sessionId,
        ])
            ->assertCreated()
            ->assertJsonPath('image_url', 'https://res.cloudinary.com/demo/image/upload/bike.jpg')
            ->assertJsonPath('body', '');
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
