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
            if (! Schema::hasColumn('sites', 'provider')) {
                $table->string('provider', 32)->default(Site::PROVIDER_AWS)->after('apex_domain');
            }

            if (! Schema::hasColumn('sites', 'provider_resource_id')) {
                $table->string('provider_resource_id')->nullable()->after('provider');
            }

            if (! Schema::hasColumn('sites', 'provider_meta')) {
                $table->json('provider_meta')->nullable()->after('provider_resource_id');
            }
        });

        DB::table('sites')
            ->whereNull('provider')
            ->orWhere('provider', '')
            ->update(['provider' => Site::PROVIDER_AWS]);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            foreach (['provider_meta', 'provider_resource_id', 'provider'] as $column) {
                if (Schema::hasColumn('sites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
