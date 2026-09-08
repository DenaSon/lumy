<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('demographic_snapshots', 'provider_position')) {
            Schema::table('demographic_snapshots', function (Blueprint $table) {
                $table->unsignedSmallInteger('provider_position')->nullable()->after('rank');
            });
        }

        $rankColumn = DB::connection()->getQueryGrammar()->wrap('rank');

        DB::table('demographic_snapshots')
            ->whereNotNull('rank')
            ->update([
                'provider_position' => DB::raw($rankColumn),
                'rank' => null,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('demographic_snapshots', 'provider_position')) {
            return;
        }

        $providerPositionColumn = DB::connection()->getQueryGrammar()->wrap('provider_position');

        DB::table('demographic_snapshots')
            ->whereNull('rank')
            ->whereNotNull('provider_position')
            ->update([
                'rank' => DB::raw($providerPositionColumn),
            ]);

        Schema::table('demographic_snapshots', function (Blueprint $table) {
            $table->dropColumn('provider_position');
        });
    }
};
