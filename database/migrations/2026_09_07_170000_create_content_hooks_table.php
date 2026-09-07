<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_hooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('type', 64)->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['content_id', 'is_primary']);
            $table->index('type');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_hooks');
    }
};
