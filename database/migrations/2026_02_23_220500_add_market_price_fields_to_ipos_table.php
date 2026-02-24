<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipos', function (Blueprint $table) {
            if (!Schema::hasColumn('ipos', 'market_price')) {
                $table->decimal('market_price', 10, 2)->nullable()->after('price_per_unit');
            }

            if (!Schema::hasColumn('ipos', 'market_price_updated_at')) {
                $table->timestamp('market_price_updated_at')->nullable()->after('market_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ipos', function (Blueprint $table) {
            if (Schema::hasColumn('ipos', 'market_price_updated_at')) {
                $table->dropColumn('market_price_updated_at');
            }
            if (Schema::hasColumn('ipos', 'market_price')) {
                $table->dropColumn('market_price');
            }
        });
    }
};
