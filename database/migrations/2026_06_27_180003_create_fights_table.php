<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fights', function (Blueprint $table) {
            $table->id();
            $table->char('ufcstats_id', 16)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('bout_order')->nullable();

            $table->string('weight_class')->nullable();
            $table->boolean('is_title_bout')->default(false);
            $table->unsignedTinyInteger('scheduled_rounds')->nullable();
            $table->string('time_format_raw')->nullable();

            $table->foreignId('red_fighter_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->foreignId('blue_fighter_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->foreignId('winner_fighter_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->enum('outcome', ['win', 'draw', 'nc', 'dq', 'overturned', 'pending'])->default('pending');

            $table->string('method')->nullable();
            $table->string('method_detail')->nullable();
            $table->unsignedTinyInteger('end_round')->nullable();
            $table->unsignedSmallInteger('end_time_sec')->nullable();
            $table->string('referee')->nullable();

            $table->timestamps();

            $table->index('event_id');
            $table->index('red_fighter_id');
            $table->index('blue_fighter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fights');
    }
};
