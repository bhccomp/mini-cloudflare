<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('included_requests_per_month')->default(0)->after('yearly_price_cents');
            $table->unsignedInteger('overage_block_size')->default(1000)->after('included_requests_per_month');
            $table->unsignedInteger('overage_price_cents')->default(0)->after('overage_block_size');
            $table->string('stripe_request_meter_id')->nullable()->after('stripe_yearly_price_id');
            $table->string('stripe_request_overage_price_id')->nullable()->after('stripe_request_meter_id');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'included_requests_per_month',
                'overage_block_size',
                'overage_price_cents',
                'stripe_request_meter_id',
                'stripe_request_overage_price_id',
            ]);
        });
    }
};
