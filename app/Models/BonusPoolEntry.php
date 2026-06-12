<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusPoolEntry extends Model
{
    protected $fillable = ['sale_id', 'level', 'amount_cents', 'reason'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
