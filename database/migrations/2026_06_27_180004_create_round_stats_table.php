<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fight_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            // round = 0 is the whole-fight total (mirrors ufcstats "Totals"); round >= 1 are per-round.
            $table->unsignedTinyInteger('round');

            $table->unsignedSmallInteger('knockdowns')->default(0);
            $table->unsignedSmallInteger('sig_str_landed')->default(0);
            $table->unsignedSmallInteger('sig_str_attempted')->default(0);
            $table->unsignedSmallInteger('total_str_landed')->default(0);
            $table->unsignedSmallInteger('total_str_attempted')->default(0);
            $table->unsignedSmallInteger('takedowns_landed')->default(0);
            $table->unsignedSmallInteger('takedowns_attempted')->default(0);
            $table->unsignedSmallInteger('sub_attempts')->default(0);
            $table->unsignedSmallInteger('reversals')->default(0);
            $table->unsignedSmallInteger('control_time_sec')->nullable();

            // Significant strikes by target.
            $table->unsignedSmallInteger('head_landed')->default(0);
            $table->unsignedSmallInteger('head_attempted')->default(0);
            $table->unsignedSmallInteger('body_landed')->default(0);
            $table->unsignedSmallInteger('body_attempted')->default(0);
            $table->unsignedSmallInteger('leg_landed')->default(0);
            $table->unsignedSmallInteger('leg_attempted')->default(0);

            // Significant strikes by position.
            $table->unsignedSmallInteger('distance_landed')->default(0);
            $table->unsignedSmallInteger('distance_attempted')->default(0);
            $table->unsignedSmallInteger('clinch_landed')->default(0);
            $table->unsignedSmallInteger('clinch_attempted')->default(0);
            $table->unsignedSmallInteger('ground_landed')->default(0);
            $table->unsignedSmallInteger('ground_attempted')->default(0);

            $table->timestamps();

            $table->unique(['fight_id', 'fighter_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_stats');
    }
};
