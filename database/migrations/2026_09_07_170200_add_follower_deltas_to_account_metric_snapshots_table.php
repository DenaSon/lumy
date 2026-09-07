<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_metric_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('followers_gained')->nullable()->after('followers_count');
            $table->unsignedBigInteger('followers_lost')->nullable()->after('followers_gained');
        });
    }

    public function down(): void
    {
        Schema::table('account_metric_snapshots', function (Blueprint $table) {
            $table->dropColumn(['followers_gained', 'followers_lost']);
        });
    }
};
