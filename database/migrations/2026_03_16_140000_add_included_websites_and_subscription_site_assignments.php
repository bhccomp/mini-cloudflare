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
            $table->unsignedInteger('included_websites')->default(1)->after('yearly_price_cents');
        });

        Schema::create('organization_subscription_site', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('site_id');
            $table->unique(['organization_subscription_id', 'site_id'], 'org_subscription_site_unique');
        });

        $now = now();

        $rows = DB::table('organization_subscriptions')
            ->whereNotNull('site_id')
            ->select(['id as organization_subscription_id', 'site_id'])
            ->get()
            ->map(fn (object $row): array => [
                'organization_subscription_id' => $row->organization_subscription_id,
                'site_id' => $row->site_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('organization_subscription_site')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subscription_site');

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('included_websites');
        });
    }
};
