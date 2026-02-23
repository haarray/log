<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('password');
            }

            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->after('role');
            }

            if (!Schema::hasColumn('users', 'receive_in_app_notifications')) {
                $table->boolean('receive_in_app_notifications')->default(true)->after('telegram_chat_id');
            }

            if (!Schema::hasColumn('users', 'receive_telegram_notifications')) {
                $table->boolean('receive_telegram_notifications')->default(false)->after('receive_in_app_notifications');
            }

            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('receive_telegram_notifications');
            }

            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code')->nullable()->after('two_factor_enabled');
            }

            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            }

            if (!Schema::hasColumn('users', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->after('two_factor_expires_at');
            }
        });
    }

    public function down(): void
    {
        $dropColumns = [];
        foreach ([
            'role',
            'telegram_chat_id',
            'receive_in_app_notifications',
            'receive_telegram_notifications',
            'two_factor_enabled',
            'two_factor_code',
            'two_factor_expires_at',
            'facebook_id',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $dropColumns[] = $column;
            }
        }

        if (empty($dropColumns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($dropColumns) {
            $table->dropColumn($dropColumns);
        });
    }
};
