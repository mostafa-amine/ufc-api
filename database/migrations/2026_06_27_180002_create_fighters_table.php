<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fighters', function (Blueprint $table) {
            $table->id();
            $table->char('ufcstats_id', 16)->unique();
            $table->string('name');
            $table->string('nickname')->nullable();

            // Physicals: parsed value + raw source string (raw kept so a parse bug never loses data).
            $table->unsignedSmallInteger('height_in')->nullable();
            $table->string('height_raw')->nullable();
            $table->unsignedSmallInteger('weight_lb')->nullable();
            $table->string('weight_raw')->nullable();
            $table->decimal('reach_in', 4, 1)->nullable();
            $table->string('reach_raw')->nullable();
            $table->string('stance')->nullable();
            $table->date('dob')->nullable();
            $table->string('dob_raw')->nullable();

            // Record.
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('no_contests')->default(0);

            // Career averages (nullable; ufcstats shows "--" when unknown).
            $table->decimal('slpm', 6, 2)->nullable();
            $table->decimal('str_acc', 5, 2)->nullable();
            $table->decimal('sapm', 6, 2)->nullable();
            $table->decimal('str_def', 5, 2)->nullable();
            $table->decimal('td_avg', 6, 2)->nullable();
            $table->decimal('td_acc', 5, 2)->nullable();
            $table->decimal('td_def', 5, 2)->nullable();
            $table->decimal('sub_avg', 6, 2)->nullable();

            $table->string('url')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->fullText(['name', 'nickname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fighters');
    }
};
