<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_name_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->timestamp('changed_at');
            $table->unique(['account_id', 'name']);
            $table->index(['account_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_name_history');
    }
};
