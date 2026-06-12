<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    // seller_id / status / approved_* are set explicitly, never mass-assigned.
    protected $fillable = ['amount_cents', 'sold_at', 'notes'];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'approved_at' => 'datetime',
            'amount_cents' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class);
    }

    public function poolEntries(): HasMany
    {
        return $this->hasMany(BonusPoolEntry::class);
    }
}
