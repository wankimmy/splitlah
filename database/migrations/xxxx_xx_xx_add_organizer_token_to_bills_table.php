<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (!Schema::hasColumn('bills', 'organizer_token')) {
                $table->string('organizer_token', 64)->nullable()->index()->after('published_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (Schema::hasColumn('bills', 'organizer_token')) {
                $table->dropIndex(['organizer_token']);
                $table->dropColumn('organizer_token');
            }
        });
    }
};
