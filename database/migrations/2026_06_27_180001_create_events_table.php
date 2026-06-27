<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->char('ufcstats_id', 16)->unique();
            $table->string('name');
            $table->date('date')->nullable();
            $table->string('location_raw')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->enum('status', ['upcoming', 'completed'])->default('completed')->index();
            $table->string('url')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
