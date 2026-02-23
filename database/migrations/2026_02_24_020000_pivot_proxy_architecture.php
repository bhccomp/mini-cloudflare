<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'display_name')) {
                $table->string('display_name')->nullable()->after('organization_id');
            }

            if (! Schema::hasColumn('sites', 'www_domain')) {
                $table->string('www_domain')->nullable()->after('apex_domain');
            }

            if (! Schema::hasColumn('sites', 'origin_type')) {
                $table->string('origin_type')->default('url')->after('www_domain');
            }

            if (! Schema::hasColumn('sites', 'origin_host')) {
                $table->string('origin_host')->nullable()->after('origin_url');
            }

            if (! Schema::hasColumn('sites', 'acm_certificate_arn')) {
                $table->string('acm_certificate_arn')->nullable()->after('waf_web_acl_arn');
            }

            if (! Schema::hasColumn('sites', 'required_dns_records')) {
                $table->json('required_dns_records')->nullable()->after('acm_certificate_arn');
            }

            if (! Schema::hasColumn('sites', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_provisioned_at');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'actor_id')) {
                $table->foreignId('actor_id')->nullable()->after('site_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('audit_logs', 'actor_id')) {
                $table->dropConstrainedForeignId('actor_id');
            }
        });

        Schema::table('sites', function (Blueprint $table): void {
            foreach (['display_name', 'www_domain', 'origin_type', 'origin_host', 'acm_certificate_arn', 'required_dns_records', 'last_error'] as $column) {
                if (Schema::hasColumn('sites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
