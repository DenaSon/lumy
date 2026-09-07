<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->index(['content_pillar_id', 'sort_order'], 'topics_pillar_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex('topics_pillar_sort_idx');
            $table->dropColumn('sort_order');
        });
    }
};
