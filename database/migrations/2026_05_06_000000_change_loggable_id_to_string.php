<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['loggable_type', 'loggable_id']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('loggable_id', 255)->nullable()->change();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['loggable_type', 'loggable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['loggable_type', 'loggable_id']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('loggable_id')->nullable()->change();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['loggable_type', 'loggable_id']);
        });
    }
};
