<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_malicious_domains', function (Blueprint $table): void {
            $table->id();
            $table->string('domain')->unique();
            $table->string('status', 32)->default('active');
            $table->string('source', 64)->default('external_feed');
            $table->text('notes')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->json('last_test_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_malicious_domains');
    }
};
