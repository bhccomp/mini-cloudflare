<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_subscribers', function (Blueprint $table): void {
            $table->text('token_encrypted')->nullable()->after('token_hash');
            $table->string('status_token_hash', 64)->nullable()->after('token_encrypted');
            $table->string('verification_token_hash', 64)->nullable()->after('status_token_hash');
            $table->timestamp('verified_at')->nullable()->after('last_seen_at');

            $table->index('status_token_hash');
            $table->index('verification_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('wordpress_subscribers', function (Blueprint $table): void {
            $table->dropIndex(['status_token_hash']);
            $table->dropIndex(['verification_token_hash']);
            $table->dropColumn(['token_encrypted', 'status_token_hash', 'verification_token_hash', 'verified_at']);
        });
    }
};
