<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->timestamp('provider_updated_at')->nullable();
            $table->string('snapshot_type', 32)->default('scheduled');
            $table->string('snapshot_window', 32)->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('reposts')->nullable();
            $table->unsignedBigInteger('follows')->nullable();
            $table->unsignedBigInteger('avg_watch_time_ms')->nullable();
            $table->unsignedBigInteger('total_watch_time_ms')->nullable();
            $table->decimal('skip_rate', 7, 4)->nullable();
            $table->decimal('video_duration_seconds', 10, 3)->nullable();
            $table->decimal('provider_engagement_rate', 9, 6)->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['content_id', 'captured_at']);
            $table->index(['content_id', 'snapshot_window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_metric_snapshots');
    }
};
