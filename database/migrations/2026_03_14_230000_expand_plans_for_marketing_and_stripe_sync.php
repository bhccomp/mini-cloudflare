<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('headline')->nullable()->after('name');
            $table->text('description')->nullable()->after('headline');
            $table->string('price_suffix')->default('/ month')->after('yearly_price_cents');
            $table->string('badge')->nullable()->after('price_suffix');
            $table->string('cta_label')->nullable()->after('badge');
            $table->boolean('is_featured')->default(false)->after('cta_label');
            $table->boolean('is_contact_only')->default(false)->after('is_featured');
            $table->boolean('show_on_marketing_site')->default(true)->after('is_contact_only');
            $table->unsignedInteger('sort_order')->default(0)->after('show_on_marketing_site');
            $table->string('currency', 3)->default('USD')->after('sort_order');
            $table->string('stripe_product_id')->nullable()->after('currency');
            $table->string('stripe_monthly_price_id')->nullable()->after('stripe_product_id');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
            $table->timestamp('stripe_synced_at')->nullable()->after('stripe_yearly_price_id');
        });

        if (DB::table('plans')->count() === 0) {
            DB::table('plans')->insert([
                [
                    'code' => 'starter',
                    'name' => 'Starter',
                    'headline' => 'Starter',
                    'description' => 'For smaller WordPress sites that need clean edge protection and email alerts.',
                    'monthly_price_cents' => 2900,
                    'yearly_price_cents' => 29000,
                    'price_suffix' => '/ month',
                    'badge' => null,
                    'cta_label' => 'Get Started',
                    'is_featured' => false,
                    'is_contact_only' => false,
                    'show_on_marketing_site' => true,
                    'sort_order' => 10,
                    'currency' => 'USD',
                    'limits' => json_encode([
                        'requests_per_month' => 500000,
                    ], JSON_THROW_ON_ERROR),
                    'features' => json_encode([
                        'Up to 500,000 requests/month',
                        'Basic edge filtering',
                        'Email alerts',
                        'Standard support',
                    ], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'growth',
                    'name' => 'Growth',
                    'headline' => 'Growth',
                    'description' => 'For growing sites that need stronger protection, faster alerts, and higher traffic room.',
                    'monthly_price_cents' => 9900,
                    'yearly_price_cents' => 99000,
                    'price_suffix' => '/ month',
                    'badge' => 'Most Popular',
                    'cta_label' => 'Start Growth',
                    'is_featured' => true,
                    'is_contact_only' => false,
                    'show_on_marketing_site' => true,
                    'sort_order' => 20,
                    'currency' => 'USD',
                    'limits' => json_encode([
                        'requests_per_month' => 5000000,
                    ], JSON_THROW_ON_ERROR),
                    'features' => json_encode([
                        'Up to 5 million requests/month',
                        'Advanced attack patterns + origin shielding',
                        'Slack + SMS + Email + Webhook alerts',
                        'Priority support',
                        'Overage: $0.02 per 1,000 extra requests',
                    ], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'enterprise',
                    'name' => 'Enterprise',
                    'headline' => 'Enterprise',
                    'description' => 'For larger teams that need dedicated onboarding, custom rule sets, and enterprise billing.',
                    'monthly_price_cents' => 0,
                    'yearly_price_cents' => 0,
                    'price_suffix' => '',
                    'badge' => null,
                    'cta_label' => 'Contact Sales',
                    'is_featured' => false,
                    'is_contact_only' => true,
                    'show_on_marketing_site' => true,
                    'sort_order' => 30,
                    'currency' => 'USD',
                    'limits' => json_encode([
                        'requests_per_month' => null,
                    ], JSON_THROW_ON_ERROR),
                    'features' => json_encode([
                        '10+ million requests/month',
                        'Dedicated edge configuration',
                        'Custom WordPress security rules',
                        'SLA + personal onboarding',
                        'Path to your own infrastructure when ready',
                    ], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'headline',
                'description',
                'price_suffix',
                'badge',
                'cta_label',
                'is_featured',
                'is_contact_only',
                'show_on_marketing_site',
                'sort_order',
                'currency',
                'stripe_product_id',
                'stripe_monthly_price_id',
                'stripe_yearly_price_id',
                'stripe_synced_at',
            ]);
        });
    }
};
