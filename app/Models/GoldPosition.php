<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'grams',
        'buy_price_per_gram',
        'current_price_per_gram',
        'source',
        'bought_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'grams' => 'decimal:3',
            'buy_price_per_gram' => 'decimal:2',
            'current_price_per_gram' => 'decimal:2',
            'bought_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investedAmount(): float
    {
        return round((float) $this->grams * (float) $this->buy_price_per_gram, 2);
    }

    public function currentValue(): float
    {
        $current = (float) ($this->current_price_per_gram ?? 0);
        if ($current <= 0) {
            return $this->investedAmount();
        }

        return round((float) $this->grams * $current, 2);
    }
}
