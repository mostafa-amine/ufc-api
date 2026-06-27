<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('incremental'); // incremental | full | event
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default('running'); // running | success | failed
            $table->unsignedInteger('events_scraped')->default(0);
            $table->unsignedInteger('fights_scraped')->default(0);
            $table->unsignedInteger('fighters_scraped')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
