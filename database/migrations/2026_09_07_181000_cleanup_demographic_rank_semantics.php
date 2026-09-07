<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demographic_snapshots', function (Blueprint $table) {
            $table->unsignedSmallInteger('provider_position')->nullable()->after('rank');
        });

        DB::table('demographic_snapshots')
            ->whereNotNull('rank')
            ->update([
                'provider_position' => DB::raw('rank'),
                'rank' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('demographic_snapshots')
            ->whereNull('rank')
            ->whereNotNull('provider_position')
            ->update([
                'rank' => DB::raw('provider_position'),
            ]);

        Schema::table('demographic_snapshots', function (Blueprint $table) {
            $table->dropColumn('provider_position');
        });
    }
};
