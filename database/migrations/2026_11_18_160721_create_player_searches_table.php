<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_searches', function (Blueprint $table) {
            $table->id();

            // Reserved for future game support; currently always "dota".
            $table->string('game', 10);
            // Dota 2 account ID.
            $table->string('identifier', 20);

            $table->string('nickname')->nullable();
            $table->string('avatar')->nullable();
            $table->unsignedInteger('search_count')->default(1);
            $table->timestamp('last_searched_at')->nullable();

            $table->timestamps();

            $table->unique(['game', 'identifier']);
            $table->index(['last_searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_searches');
    }
};
