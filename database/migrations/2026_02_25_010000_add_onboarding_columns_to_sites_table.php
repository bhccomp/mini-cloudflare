<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'onboarding_status')) {
                $table->string('onboarding_status', 64)
                    ->default(Site::ONBOARDING_DRAFT)
                    ->after('provider_meta');
            }

            if (! Schema::hasColumn('sites', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('last_provisioned_at');
            }
        });

        DB::table('sites')
            ->whereNull('onboarding_status')
            ->orWhere('onboarding_status', '')
            ->update(['onboarding_status' => Site::ONBOARDING_DRAFT]);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (Schema::hasColumn('sites', 'last_checked_at')) {
                $table->dropColumn('last_checked_at');
            }

            if (Schema::hasColumn('sites', 'onboarding_status')) {
                $table->dropColumn('onboarding_status');
            }
        });
    }
};
