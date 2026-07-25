<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('evaluation_status')->nullable();
        });

        DB::table('candidates')
            ->whereExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('analyses')
                    ->whereColumn('analyses.candidate_id', 'candidates.id');
            })
            ->update(['evaluation_status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('evaluation_status');
        });
    }
};
