<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('captured_at');
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('accounts_engaged')->nullable();
            $table->unsignedBigInteger('total_interactions')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('reposts')->nullable();
            $table->bigInteger('follows_and_unfollows')->nullable();
            $table->unsignedBigInteger('profile_links_taps')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['social_account_id', 'captured_at'], 'acct_metrics_account_captured_idx');
            $table->index(
                ['social_account_id', 'period_start', 'period_end'],
                'acct_metrics_account_period_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_metric_snapshots');
    }
};
