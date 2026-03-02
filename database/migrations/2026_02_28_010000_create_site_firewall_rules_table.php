<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_firewall_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_rule_id')->nullable();
            $table->string('rule_type', 32);
            $table->string('target', 191);
            $table->string('action', 32)->default('block');
            $table->string('mode', 32)->default('enforced');
            $table->string('status', 32)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'mode']);
            $table->index(['site_id', 'rule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_firewall_rules');
    }
};
