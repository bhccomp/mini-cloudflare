<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_connection_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'expires_at']);
        });

        Schema::create('plugin_site_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('site_token_hash', 64);
            $table->string('status', 32)->default('connected');
            $table->string('home_url')->nullable();
            $table->string('site_url')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('plugin_version', 32)->nullable();
            $table->json('last_report_payload')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();

            $table->unique('site_id');
            $table->index('site_token_hash');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_site_connections');
        Schema::dropIfExists('plugin_connection_tokens');
    }
};
