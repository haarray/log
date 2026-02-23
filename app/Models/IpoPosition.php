<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpoPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ipo_id',
        'units_applied',
        'units_allotted',
        'sold_units',
        'invested_amount',
        'current_price',
        'sold_amount',
        'status',
        'applied_at',
        'sold_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invested_amount' => 'decimal:2',
            'current_price' => 'decimal:2',
            'sold_amount' => 'decimal:2',
            'applied_at' => 'date',
            'sold_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ipo(): BelongsTo
    {
        return $this->belongsTo(IPO::class, 'ipo_id');
    }

    public function unrealizedGain(): float
    {
        $currentPrice = (float) ($this->current_price ?? 0);
        $units = max((int) $this->units_allotted, 0);

        if ($currentPrice <= 0 || $units <= 0) {
            return 0.0;
        }

        return round(($currentPrice * $units) - (float) $this->invested_amount, 2);
    }
}
