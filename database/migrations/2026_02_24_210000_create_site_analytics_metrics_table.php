<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_analytics_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('blocked_requests_24h')->nullable();
            $table->unsignedBigInteger('allowed_requests_24h')->nullable();
            $table->unsignedBigInteger('total_requests_24h')->nullable();
            $table->decimal('cache_hit_ratio', 5, 2)->nullable();
            $table->unsignedBigInteger('cached_requests_24h')->nullable();
            $table->unsignedBigInteger('origin_requests_24h')->nullable();
            $table->json('trend_labels')->nullable();
            $table->json('blocked_trend')->nullable();
            $table->json('allowed_trend')->nullable();
            $table->json('regional_traffic')->nullable();
            $table->json('regional_threat')->nullable();
            $table->json('source')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_analytics_metrics');
    }
};
