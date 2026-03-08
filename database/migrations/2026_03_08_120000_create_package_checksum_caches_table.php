<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_checksum_caches', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->string('slug');
            $table->string('version', 64);
            $table->json('checksums')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug', 'version']);
            $table->index(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_checksum_caches');
    }
};
