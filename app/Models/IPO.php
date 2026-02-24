<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IPO extends Model
{
    use HasFactory;

    protected $table = 'ipos';

    protected $fillable = [
        'symbol',
        'company_name',
        'status',
        'open_date',
        'close_date',
        'price_per_unit',
        'market_price',
        'market_price_updated_at',
        'min_units',
        'max_units',
        'listing_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'open_date' => 'date',
            'close_date' => 'date',
            'listing_date' => 'date',
            'price_per_unit' => 'decimal:2',
            'market_price' => 'decimal:2',
            'market_price_updated_at' => 'datetime',
        ];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(IpoPosition::class, 'ipo_id');
    }
}
