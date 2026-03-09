<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_signature_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sample_type', 32)->default('malware');
            $table->string('family')->nullable();
            $table->string('language', 32)->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->longText('content')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->json('signals')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_signature_samples');
    }
};
