<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demographic_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->string('dimension_type', 32);
            $table->string('dimension');
            $table->unsignedBigInteger('value');
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->json('provider_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique([
                'social_account_id',
                'captured_at',
                'dimension_type',
                'dimension',
            ], 'demographic_snapshots_unique_dimension');
            $table->index(
                ['social_account_id', 'dimension_type', 'captured_at'],
                'demographics_account_dimension_captured_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demographic_snapshots');
    }
};
