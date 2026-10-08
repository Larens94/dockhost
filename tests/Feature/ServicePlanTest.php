<?php


// ServicePlanTest.php — ServicePlanTest module.
//
// exports: ServicePlanTest | ServicePlanTest::test_authenticated_user_can_create_a_service_plan(): void | ServicePlanTest::test_service_plan_index_renders_inertia_page(): void | ServicePlanTest::test_guests_cannot_create_a_service_plan(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\ServicePlan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicePlanTest extends TestCase
{
    public function test_authenticated_user_can_create_a_service_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('service-plans.store'), [
            'name' => 'Unlimited',
            'slug' => 'unlimited',
        ]);

        $plan = ServicePlan::query()->where('slug', 'unlimited')->first();

        $this->assertNotNull($plan);
        $response->assertRedirect(route('service-plans.show', $plan));
        $this->assertNull($plan->max_domains);
    }

    public function test_service_plan_index_renders_inertia_page(): void
    {
        $user = User::factory()->create();
        ServicePlan::factory()->unlimited()->create();

        $this->actingAs($user)
            ->get(route('service-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ServicePlans/Index')
                ->has('plans', 1)
                ->where('plans.0.slug', 'unlimited'));
    }

    public function test_guests_cannot_create_a_service_plan(): void
    {
        $this->post(route('service-plans.store'), [
            'name' => 'Unlimited',
        ])->assertRedirect(route('login'));
    }
}
