<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->string('provider', 32);
            $table->string('username');
            $table->string('display_name')->nullable();
            $table->string('account_type', 64)->nullable();
            $table->string('platform_account_id')->nullable();
            $table->string('provider_account_id')->nullable();
            $table->string('provider_profile_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['platform', 'username']);
            $table->index(['provider', 'provider_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
