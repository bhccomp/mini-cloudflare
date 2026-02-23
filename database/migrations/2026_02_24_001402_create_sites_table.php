<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('apex_domain');
            $table->string('environment')->default('prod');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->string('origin_url')->nullable();
            $table->string('origin_ip')->nullable();
            $table->string('provisioning_status')->default('not_provisioned');
            $table->string('cloudfront_distribution_id')->nullable();
            $table->string('cloudfront_domain_name')->nullable();
            $table->string('waf_web_acl_arn')->nullable();
            $table->boolean('under_attack_mode_enabled')->default(false);
            $table->timestamp('last_provisioned_at')->nullable();
            $table->text('last_provision_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'apex_domain', 'environment']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
