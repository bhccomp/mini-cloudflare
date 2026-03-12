<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_repo_sync_hashes', function (Blueprint $table): void {
            $table->id();
            $table->string('algorithm', 16);
            $table->string('hash_value', 64);
            $table->string('status', 32)->default('active');
            $table->string('source', 64)->default('romainmarcoux_malicious_hash');
            $table->text('notes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['algorithm', 'hash_value']);
            $table->index(['status', 'algorithm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_repo_sync_hashes');
    }
};
