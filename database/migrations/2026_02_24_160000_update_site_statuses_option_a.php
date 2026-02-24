<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sites')
            ->where('status', 'pending_dns')
            ->update(['status' => Site::STATUS_PENDING_DNS_VALIDATION]);

        DB::table('sites')
            ->where('status', 'provisioning')
            ->update(['status' => Site::STATUS_DEPLOYING]);
    }

    public function down(): void
    {
        DB::table('sites')
            ->where('status', Site::STATUS_PENDING_DNS_VALIDATION)
            ->update(['status' => 'pending_dns']);

        DB::table('sites')
            ->where('status', Site::STATUS_DEPLOYING)
            ->update(['status' => 'provisioning']);
    }
};
