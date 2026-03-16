<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('early_access_leads', function (Blueprint $table): void {
            $table->string('websites_managed')->nullable()->after('monthly_requests_band');
        });
    }

    public function down(): void
    {
        Schema::table('early_access_leads', function (Blueprint $table): void {
            $table->dropColumn('websites_managed');
        });
    }
};
