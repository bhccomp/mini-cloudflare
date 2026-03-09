<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('site_host', 191)->unique();
            $table->string('home_url')->nullable();
            $table->string('site_url')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('plugin_version', 32)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->string('token_hash', 64)->index();
            $table->string('status', 32)->default('active');
            $table->timestamp('last_token_issued_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index('marketing_opt_in');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_subscribers');
    }
};
