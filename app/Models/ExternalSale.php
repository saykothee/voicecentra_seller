<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id', 'sale_date', 'paid_at', 'amount_cents', 'paid', 'free_trial',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'paid_at' => 'datetime',
            'amount_cents' => 'integer',
            'paid' => 'boolean',
            'free_trial' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
