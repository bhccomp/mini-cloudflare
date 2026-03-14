<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_active_marketing_plans_from_database(): void
    {
        Plan::query()->delete();

        Plan::create([
            'code' => 'starter',
            'name' => 'Starter',
            'headline' => 'Starter',
            'description' => 'Starter description',
            'monthly_price_cents' => 4900,
            'yearly_price_cents' => 49000,
            'price_suffix' => '/ month',
            'badge' => null,
            'cta_label' => 'Start Starter',
            'is_featured' => false,
            'is_contact_only' => false,
            'show_on_marketing_site' => true,
            'sort_order' => 10,
            'currency' => 'USD',
            'features' => ['Feature A', 'Feature B'],
            'is_active' => true,
        ]);

        Plan::create([
            'code' => 'hidden',
            'name' => 'Hidden',
            'headline' => 'Hidden',
            'description' => 'Should not render',
            'monthly_price_cents' => 19900,
            'yearly_price_cents' => 199000,
            'price_suffix' => '/ month',
            'cta_label' => 'Hidden',
            'is_featured' => false,
            'is_contact_only' => false,
            'show_on_marketing_site' => false,
            'sort_order' => 20,
            'currency' => 'USD',
            'features' => ['Invisible'],
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Starter');
        $response->assertSee('$49');
        $response->assertSee('Starter description');
        $response->assertSee('Feature A');
        $response->assertDontSee('Should not render');
    }
}
