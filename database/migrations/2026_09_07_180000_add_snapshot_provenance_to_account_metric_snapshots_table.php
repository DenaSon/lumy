<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_metric_snapshots', function (Blueprint $table) {
            $table->string('snapshot_type', 32)->nullable()->after('captured_at');
            $table->timestamp('provider_updated_at')->nullable()->after('snapshot_type');

            $table->unique(
                ['social_account_id', 'snapshot_type', 'provider_updated_at'],
                'acct_metrics_snapshot_provider_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('account_metric_snapshots', function (Blueprint $table) {
            $table->dropUnique('acct_metrics_snapshot_provider_unique');
            $table->dropColumn(['snapshot_type', 'provider_updated_at']);
        });
    }
};
