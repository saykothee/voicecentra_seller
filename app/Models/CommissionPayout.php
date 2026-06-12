<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPayout extends Model
{
    // Only ever created by CommissionDistributor (server-side), never from request input.
    protected $fillable = [
        'sale_id', 'recipient_id', 'level', 'rate_numerator',
        'amount_cents', 'recipient_was_active', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'recipient_was_active' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
