<?php


// CustomerTest.php — CustomerTest module.
//
// exports: CustomerTest | CustomerTest::test_authenticated_user_can_create_a_customer(): void | CustomerTest::test_customer_index_renders_inertia_page(): void | CustomerTest::test_authenticated_user_can_update_a_customer(): void | CustomerTest::test_authenticated_user_can_delete_a_customer_without_spaces(): void | CustomerTest::test_cannot_delete_a_customer_with_spaces(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_create_a_customer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Acme Hosting',
            'email' => 'ops@acme.test',
            'notes' => 'Primary tenant',
        ]);

        $customer = Customer::query()->where('name', 'Acme Hosting')->first();

        $this->assertNotNull($customer);
        $response->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', [
            'name' => 'Acme Hosting',
            'email' => 'ops@acme.test',
        ]);
    }

    public function test_customer_index_renders_inertia_page(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['name' => 'Beta Co']);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers', 1)
                ->where('customers.0.name', 'Beta Co'));
    }

    public function test_authenticated_user_can_update_a_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create([
            'name' => 'Acme Hosting',
            'email' => 'ops@acme.test',
        ]);

        $this->actingAs($user)
            ->put(route('customers.update', $customer), [
                'name' => 'Acme Renamed',
                'email' => 'hello@acme.test',
                'notes' => 'Updated',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Acme Renamed',
            'email' => 'hello@acme.test',
            'notes' => 'Updated',
        ]);
    }

    public function test_authenticated_user_can_delete_a_customer_without_spaces(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_cannot_delete_a_customer_with_spaces(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        Subscription::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user)
            ->from(route('customers.show', $customer))
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHasErrors('customer');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
