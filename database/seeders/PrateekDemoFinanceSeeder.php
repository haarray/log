<?php

namespace Database\Seeders;

use App\Http\Services\MLSuggestionService;
use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\GoldPosition;
use App\Models\IPO;
use App\Models\IpoPosition;
use App\Models\Suggestion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PrateekDemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()
            ->where('email', 'prateekbhujelpb@gmail.com')
            ->orWhere('name', 'like', '%prateek%')
            ->first();

        if (!$user) {
            if ($this->command) {
                $this->command->warn('Prateek demo user not found. Create the user first.');
            }

            return;
        }

        $uid = (int) $user->id;

        DB::transaction(function () use ($uid): void {
            Transaction::query()->where('user_id', $uid)->delete();
            IpoPosition::query()->where('user_id', $uid)->delete();
            GoldPosition::query()->where('user_id', $uid)->delete();
            Suggestion::query()->where('user_id', $uid)->delete();
            Account::query()->where('user_id', $uid)->delete();
            ExpenseCategory::query()->where('user_id', $uid)->where('is_default', false)->delete();
        });

        $accounts = [
            'bank_nabil' => Account::query()->create([
                'user_id' => $uid,
                'name' => 'Nabil Savings',
                'institution' => 'Nabil Bank',
                'type' => 'bank',
                'currency' => 'NPR',
                'balance' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ]),
            'bank_nic' => Account::query()->create([
                'user_id' => $uid,
                'name' => 'NIC Salary A/C',
                'institution' => 'NIC Asia',
                'type' => 'bank',
                'currency' => 'NPR',
                'balance' => 0,
                'sort_order' => 2,
                'is_active' => true,
            ]),
            'esewa' => Account::query()->create([
                'user_id' => $uid,
                'name' => 'eSewa Wallet',
                'institution' => 'eSewa',
                'type' => 'esewa',
                'currency' => 'NPR',
                'balance' => 0,
                'sort_order' => 3,
                'is_active' => true,
            ]),
            'khalti' => Account::query()->create([
                'user_id' => $uid,
                'name' => 'Khalti Wallet',
                'institution' => 'Khalti',
                'type' => 'khalti',
                'currency' => 'NPR',
                'balance' => 0,
                'sort_order' => 4,
                'is_active' => true,
            ]),
            'cash' => Account::query()->create([
                'user_id' => $uid,
                'name' => 'Cash Wallet',
                'institution' => 'Personal',
                'type' => 'cash',
                'currency' => 'NPR',
                'balance' => 0,
                'sort_order' => 5,
                'is_active' => true,
            ]),
        ];

        $categoryMap = [];
        $categorySeed = [
            ['salary', 'Salary', 'fa-solid fa-briefcase', '#2f7df6'],
            ['food', 'Food', 'fa-solid fa-utensils', '#f59e0b'],
            ['transport', 'Transport', 'fa-solid fa-bus', '#06b6d4'],
            ['rent', 'Rent', 'fa-solid fa-house', '#fb7185'],
            ['utilities', 'Utilities', 'fa-solid fa-bolt', '#818cf8'],
            ['family', 'Family', 'fa-solid fa-people-roof', '#14b8a6'],
            ['health', 'Health', 'fa-solid fa-heart-pulse', '#ef4444'],
            ['entertainment', 'Entertainment', 'fa-solid fa-film', '#a78bfa'],
            ['investment', 'Investment', 'fa-solid fa-chart-line', '#22c55e'],
            ['shopping', 'Shopping', 'fa-solid fa-bag-shopping', '#f97316'],
        ];

        foreach ($categorySeed as [$slug, $name, $icon, $color]) {
            $category = ExpenseCategory::query()->firstOrCreate(
                ['user_id' => $uid, 'slug' => $slug],
                ['name' => $name, 'icon' => $icon, 'color' => $color, 'is_default' => false]
            );
            $categoryMap[$slug] = $category;
        }

        $addTx = function (
            string $accountKey,
            string $type,
            float $amount,
            string $categorySlug,
            string $title,
            int $daysAgo,
            ?string $notes = null
        ) use (&$accounts, $categoryMap, $uid): void {
            $account = $accounts[$accountKey] ?? null;
            if (!$account) {
                return;
            }

            $category = $categoryMap[$categorySlug] ?? null;
            $date = Carbon::today()->subDays($daysAgo)->toDateString();

            Transaction::query()->create([
                'user_id' => $uid,
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'source' => 'manual',
                'type' => $type,
                'amount' => $amount,
                'title' => $title,
                'notes' => $notes,
                'transaction_date' => $date,
            ]);

            $delta = $type === 'credit' ? $amount : -$amount;
            $account->balance = round(((float) $account->balance + $delta), 2);
            $account->save();
            $accounts[$accountKey] = $account->fresh();
        };

        $addTx('bank_nic', 'credit', 62000, 'salary', 'Monthly Salary', 86, 'Falgun salary cycle');
        $addTx('bank_nic', 'credit', 62000, 'salary', 'Monthly Salary', 56, 'Chaitra salary cycle');
        $addTx('bank_nic', 'credit', 64000, 'salary', 'Monthly Salary', 26, 'Baisakh salary cycle + increment');
        $addTx('bank_nabil', 'credit', 18000, 'salary', 'Freelance Payment', 17, 'Landing page project');

        $addTx('bank_nic', 'debit', 17000, 'rent', 'Home Rent', 82, 'Monthly rent');
        $addTx('bank_nic', 'debit', 17000, 'rent', 'Home Rent', 52, 'Monthly rent');
        $addTx('bank_nic', 'debit', 17000, 'rent', 'Home Rent', 22, 'Monthly rent');
        $addTx('bank_nic', 'debit', 4200, 'utilities', 'Internet + Electricity', 78, 'Utility bill');
        $addTx('bank_nic', 'debit', 4600, 'utilities', 'Internet + Electricity', 48, 'Utility bill');
        $addTx('bank_nic', 'debit', 4450, 'utilities', 'Internet + Electricity', 18, 'Utility bill');

        $addTx('bank_nic', 'debit', 7000, 'transport', 'Topup eSewa', 70, 'Wallet top-up');
        $addTx('esewa', 'credit', 7000, 'transport', 'eSewa Load', 70, 'From bank');
        $addTx('bank_nic', 'debit', 5000, 'shopping', 'Topup Khalti', 44, 'Wallet top-up');
        $addTx('khalti', 'credit', 5000, 'shopping', 'Khalti Load', 44, 'From bank');
        $addTx('bank_nabil', 'debit', 6000, 'food', 'Cash Withdraw', 30, 'ATM withdrawal');
        $addTx('cash', 'credit', 6000, 'food', 'Cash in Hand', 30, 'From bank');

        $foodDays = [84,81,79,76,74,71,68,66,63,60,58,55,53,50,47,45,42,39,37,34,32,29,27,24,21,19,16,14,11,9,6,4,2];
        foreach ($foodDays as $idx => $day) {
            $amount = [240, 310, 180, 420, 275, 350, 220, 390][$idx % 8];
            $accKey = ($idx % 3 === 0) ? 'esewa' : (($idx % 3 === 1) ? 'cash' : 'khalti');
            $addTx($accKey, 'debit', $amount, 'food', 'Food & Snacks', $day, 'Daily spending pattern');
        }

        $transportDays = [80,73,67,61,54,49,43,36,31,25,20,13,8,3];
        foreach ($transportDays as $idx => $day) {
            $amount = [120, 180, 95, 160, 210][$idx % 5];
            $accKey = ($idx % 2 === 0) ? 'esewa' : 'khalti';
            $addTx($accKey, 'debit', $amount, 'transport', 'Ride/Transport', $day, 'Pathao/Bus commute');
        }

        $entertainmentDays = [69,41,12];
        foreach ($entertainmentDays as $idx => $day) {
            $amount = [850, 1200, 640][$idx];
            $addTx('khalti', 'debit', $amount, 'entertainment', 'Entertainment', $day, 'Movies/subscriptions');
        }

        $familyDays = [64,33,7];
        foreach ($familyDays as $idx => $day) {
            $amount = [2500, 1800, 2200][$idx];
            $addTx('bank_nabil', 'debit', $amount, 'family', 'Family Support', $day, 'Parents support');
        }

        $healthDays = [58,15];
        foreach ($healthDays as $idx => $day) {
            $amount = [1100, 2400][$idx];
            $addTx('cash', 'debit', $amount, 'health', 'Medical Expense', $day, 'Checkup/medicines');
        }

        $addTx('bank_nabil', 'debit', 1000, 'investment', 'IPO Application - NABILB', 38, 'Primary issue payment');
        $addTx('bank_nabil', 'debit', 1500, 'investment', 'Gold Purchase Advance', 10, 'Jewelry booking');

        $ipo1 = IPO::query()->updateOrCreate(
            ['symbol' => 'NABILB'],
            [
                'company_name' => 'Nabil Bank Limited IPO',
                'status' => 'open',
                'open_date' => Carbon::today()->subDay()->toDateString(),
                'close_date' => Carbon::today()->addDays(2)->toDateString(),
                'price_per_unit' => 100,
                'market_price' => 118,
                'market_price_updated_at' => now(),
                'min_units' => 10,
                'notes' => 'Demo open IPO',
            ]
        );

        IPO::query()->updateOrCreate(
            ['symbol' => 'PALPA'],
            [
                'company_name' => 'Palpa Cements Industries Limited',
                'status' => 'upcoming',
                'open_date' => Carbon::today()->addDays(4)->toDateString(),
                'close_date' => Carbon::today()->addDays(9)->toDateString(),
                'price_per_unit' => 100,
                'min_units' => 10,
                'notes' => 'Demo upcoming IPO',
            ]
        );

        $ipo3 = IPO::query()->updateOrCreate(
            ['symbol' => 'CHDC'],
            [
                'company_name' => 'Chhyangdi Hydropower IPO',
                'status' => 'closed',
                'open_date' => Carbon::today()->subDays(65)->toDateString(),
                'close_date' => Carbon::today()->subDays(60)->toDateString(),
                'listing_date' => Carbon::today()->subDays(20)->toDateString(),
                'price_per_unit' => 100,
                'market_price' => 146,
                'market_price_updated_at' => now(),
                'min_units' => 10,
                'notes' => 'Demo listed IPO',
            ]
        );

        IpoPosition::query()->create([
            'user_id' => $uid,
            'ipo_id' => $ipo1->id,
            'status' => 'applied',
            'units_applied' => 10,
            'units_allotted' => 0,
            'sold_units' => 0,
            'invested_amount' => 1000,
            'current_price' => 0,
            'applied_at' => Carbon::today()->subDays(1)->toDateString(),
            'notes' => 'Applied via MeroShare',
        ]);

        IpoPosition::query()->create([
            'user_id' => $uid,
            'ipo_id' => $ipo3->id,
            'status' => 'allotted',
            'units_applied' => 10,
            'units_allotted' => 10,
            'sold_units' => 0,
            'invested_amount' => 1000,
            'current_price' => 146,
            'applied_at' => Carbon::today()->subDays(64)->toDateString(),
            'notes' => 'Allotted and holding',
        ]);

        GoldPosition::query()->create([
            'user_id' => $uid,
            'label' => '24k Bullion',
            'grams' => 5.500,
            'buy_price_per_gram' => 11850,
            'current_price_per_gram' => 12600,
            'source' => 'NMB Jewellers',
            'bought_at' => Carbon::today()->subDays(40)->toDateString(),
            'notes' => 'Long-term hold',
        ]);

        GoldPosition::query()->create([
            'user_id' => $uid,
            'label' => '22k Jewelry',
            'grams' => 3.250,
            'buy_price_per_gram' => 10900,
            'current_price_per_gram' => 11450,
            'source' => 'Family Gold Shop',
            'bought_at' => Carbon::today()->subDays(12)->toDateString(),
            'notes' => 'Gift purchase',
        ]);

        app(MLSuggestionService::class)->generateForUser($user);

        if ($this->command) {
            $this->command->info('Prateek demo finance data seeded.');
            $this->command->line('Accounts: ' . Account::query()->where('user_id', $uid)->count());
            $this->command->line('Transactions: ' . Transaction::query()->where('user_id', $uid)->count());
            $this->command->line('IPO Positions: ' . IpoPosition::query()->where('user_id', $uid)->count());
            $this->command->line('Gold Positions: ' . GoldPosition::query()->where('user_id', $uid)->count());
            $this->command->line('Suggestions: ' . Suggestion::query()->where('user_id', $uid)->count());
            $this->command->line('Total balance: NPR ' . number_format((float) Account::query()->where('user_id', $uid)->sum('balance'), 2));
        }
    }
}
