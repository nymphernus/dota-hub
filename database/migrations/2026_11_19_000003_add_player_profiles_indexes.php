<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            // Profile freshness checks read updated_at for a known account_id on
            // every request. A composite index keeps that lookup index-only.
            $table->index(['account_id', 'updated_at'], 'player_profiles_account_freshness_index');
        });
    }

    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropIndex('player_profiles_account_freshness_index');
        });
    }
};
