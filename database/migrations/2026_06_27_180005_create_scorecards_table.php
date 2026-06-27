<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fight_id')->constrained()->cascadeOnDelete();
            $table->string('judge_name');
            $table->unsignedTinyInteger('red_score')->nullable();
            $table->unsignedTinyInteger('blue_score')->nullable();
            $table->timestamps();

            $table->index('fight_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorecards');
    }
};
