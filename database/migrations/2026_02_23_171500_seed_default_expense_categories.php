<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = [
            ['name' => 'Food', 'icon' => 'fa-solid fa-utensils', 'color' => '#f59e0b'],
            ['name' => 'Transport', 'icon' => 'fa-solid fa-bus', 'color' => '#38bdf8'],
            ['name' => 'Utilities', 'icon' => 'fa-solid fa-bolt', 'color' => '#f97316'],
            ['name' => 'Health', 'icon' => 'fa-solid fa-heart-pulse', 'color' => '#ef4444'],
            ['name' => 'Shopping', 'icon' => 'fa-solid fa-bag-shopping', 'color' => '#a855f7'],
            ['name' => 'Salary', 'icon' => 'fa-solid fa-wallet', 'color' => '#22c55e'],
            ['name' => 'Investment', 'icon' => 'fa-solid fa-chart-line', 'color' => '#14b8a6'],
            ['name' => 'Other', 'icon' => 'fa-solid fa-shapes', 'color' => '#64748b'],
        ];

        foreach ($defaults as $item) {
            $slug = Str::slug((string) $item['name']);

            $exists = DB::table('expense_categories')
                ->whereNull('user_id')
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('expense_categories')->insert([
                'user_id' => null,
                'name' => $item['name'],
                'slug' => $slug,
                'icon' => $item['icon'],
                'color' => $item['color'],
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('expense_categories')
            ->whereNull('user_id')
            ->where('is_default', true)
            ->delete();
    }
};
