<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('facebook_id');
            }

            if (!Schema::hasColumn('users', 'terms_version')) {
                $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
            }
        });
    }

    public function down(): void
    {
        $dropColumns = [];

        foreach (['terms_accepted_at', 'terms_version'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $dropColumns[] = $column;
            }
        }

        if (!empty($dropColumns)) {
            Schema::table('users', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }
    }
};
