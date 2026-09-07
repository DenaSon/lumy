<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('primary_pillar_id')->nullable()->constrained('content_pillars')->nullOnDelete();
            $table->string('goal', 64)->nullable();
            $table->string('cta_type', 64)->nullable();
            $table->string('target_audience', 128)->nullable();
            $table->string('production_style', 128)->nullable();
            $table->string('cover_style', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('annotated_at')->nullable();
            $table->timestamps();

            $table->index('goal');
            $table->index('cta_type');
            $table->index('primary_pillar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_annotations');
    }
};
