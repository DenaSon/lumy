<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('platform_media_id')->nullable();
            $table->string('type', 64);
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('remote_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('local_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['content_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_media');
    }
};
