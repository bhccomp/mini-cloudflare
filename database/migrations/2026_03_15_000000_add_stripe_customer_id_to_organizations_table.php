<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('stripe_customer_id')->nullable()->after('billing_email')->index();
        });

        $latestSubscriptionCustomers = DB::table('organization_subscriptions')
            ->select('organization_id', DB::raw('MAX(id) as latest_id'))
            ->whereNotNull('stripe_customer_id')
            ->groupBy('organization_id')
            ->get();

        foreach ($latestSubscriptionCustomers as $row) {
            $customerId = DB::table('organization_subscriptions')
                ->where('id', $row->latest_id)
                ->value('stripe_customer_id');

            if ($customerId) {
                DB::table('organizations')
                    ->where('id', $row->organization_id)
                    ->whereNull('stripe_customer_id')
                    ->update(['stripe_customer_id' => $customerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('stripe_customer_id');
        });
    }
};
