<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'www_enabled')) {
                $table->boolean('www_enabled')->default(false)->after('apex_domain');
            }

            if (! Schema::hasColumn('sites', 'under_attack')) {
                $table->boolean('under_attack')->default(false)->after('required_dns_records');
            }
        });

        DB::table('sites')->update([
            'www_enabled' => DB::raw("CASE WHEN www_domain IS NOT NULL AND www_domain != '' THEN TRUE ELSE FALSE END"),
            'under_attack' => DB::raw('COALESCE(under_attack_mode_enabled, FALSE)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (Schema::hasColumn('sites', 'under_attack')) {
                $table->dropColumn('under_attack');
            }

            if (Schema::hasColumn('sites', 'www_enabled')) {
                $table->dropColumn('www_enabled');
            }
        });
    }
};
