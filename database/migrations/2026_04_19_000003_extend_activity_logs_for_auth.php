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
            $table->string('loggable_type')->nullable()->change();
            $table->unsignedBigInteger('loggable_id')->nullable()->change();
            $table->string('ip_address', 45)->nullable()->after('action');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['loggable_type', 'loggable_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['loggable_type', 'loggable_id']);
            $table->dropIndex(['action']);
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('loggable_type')->nullable(false)->change();
            $table->unsignedBigInteger('loggable_id')->nullable(false)->change();
            $table->index(['loggable_type', 'loggable_id']);
        });
    }
};
