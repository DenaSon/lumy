<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform_post_id');
            $table->string('provider_post_id')->nullable();
            $table->text('permalink')->nullable();
            $table->string('media_product_type', 64)->nullable();
            $table->string('content_type', 64)->nullable();
            $table->text('caption')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('analytics_status', 32)->default('pending');
            $table->string('platform_status', 32)->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'platform_post_id']);
            $table->index(['social_account_id', 'published_at']);
            $table->index(['social_account_id', 'analytics_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
