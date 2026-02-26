<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edge_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('event_at')->index();
            $table->string('ip', 64)->nullable()->index();
            $table->string('country', 8)->nullable()->index();
            $table->string('method', 16)->nullable();
            $table->string('host', 255)->nullable()->index();
            $table->text('path')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->string('action', 32)->nullable()->index();
            $table->string('rule', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'event_at']);
            $table->index(['site_id', 'country']);
            $table->index(['site_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_request_logs');
    }
};
