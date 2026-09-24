<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_and_update_a_client(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Northwind',
                'company' => 'Northwind Ltd',
                'email' => 'ops@northwind.test',
                'billing_email' => 'billing@northwind.test',
                'status' => 'active',
            ])
            ->assertRedirect(route('clients.index'));

        $client = Client::query()->where('email', 'ops@northwind.test')->firstOrFail();
        $this->assertSame('none', $client->billing_status);

        $this->actingAs($user)
            ->patch(route('clients.update', $client), [
                'name' => 'Northwind Studio',
                'company' => 'Northwind Ltd',
                'email' => 'ops@northwind.test',
                'billing_email' => 'billing@northwind.test',
                'status' => 'suspended',
            ])
            ->assertRedirect(route('clients.index'));

        $this->assertSame('suspended', $client->fresh()->status);
        $this->assertSame('none', $client->fresh()->billing_status);
        $this->assertDatabaseHas('audits', ['action' => 'client.created']);
    }
}
