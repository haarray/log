<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->string('color', 24)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('institution')->nullable();
            $table->string('type', 32)->default('bank');
            $table->string('currency', 8)->default('NPR');
            $table->decimal('balance', 14, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('source', 32)->default('manual');
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 14, 2);
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('ipos', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32)->nullable()->index();
            $table->string('company_name');
            $table->enum('status', ['open', 'upcoming', 'closed'])->default('upcoming');
            $table->date('open_date')->nullable();
            $table->date('close_date')->nullable();
            $table->decimal('price_per_unit', 10, 2)->default(100);
            $table->unsignedInteger('min_units')->default(10);
            $table->unsignedInteger('max_units')->nullable();
            $table->date('listing_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ipo_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ipo_id')->constrained('ipos')->cascadeOnDelete();
            $table->unsignedInteger('units_applied')->default(0);
            $table->unsignedInteger('units_allotted')->default(0);
            $table->unsignedInteger('sold_units')->default(0);
            $table->decimal('invested_amount', 14, 2)->default(0);
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('sold_amount', 14, 2)->nullable();
            $table->enum('status', ['applied', 'allotted', 'sold', 'cancelled'])->default('applied');
            $table->date('applied_at')->nullable();
            $table->date('sold_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('gold_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->decimal('grams', 12, 3);
            $table->decimal('buy_price_per_gram', 12, 2);
            $table->decimal('current_price_per_gram', 12, 2)->nullable();
            $table->string('source')->nullable();
            $table->date('bought_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'bought_at']);
        });

        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type', 64)->default('insight');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->string('icon', 32)->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('chat_id', 64)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('suggestions');
        Schema::dropIfExists('gold_positions');
        Schema::dropIfExists('ipo_positions');
        Schema::dropIfExists('ipos');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('expense_categories');
    }
};
