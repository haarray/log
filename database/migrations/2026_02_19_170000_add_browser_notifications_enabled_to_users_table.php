<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'browser_notifications_enabled')) {
                $table->boolean('browser_notifications_enabled')
                    ->default(true)
                    ->after('receive_telegram_notifications');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'browser_notifications_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('browser_notifications_enabled');
        });
    }
};
