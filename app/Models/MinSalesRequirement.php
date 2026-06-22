<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MinSalesRequirement extends Model
{
    protected $fillable = ['min_age', 'max_age', 'min_sales'];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'min_sales' => 'integer',
        ];
    }

    /**
     * The bracket that contains the given age. Brackets are non-overlapping,
     * so this matches at most one row. A null max_age means "no upper limit".
     */
    public function scopeForAge(Builder $query, int $age): Builder
    {
        return $query->where('min_age', '<=', $age)
            ->where(function (Builder $q) use ($age) {
                $q->whereNull('max_age')->orWhere('max_age', '>=', $age);
            });
    }

    public function label(): string
    {
        return $this->max_age === null
            ? "{$this->min_age}+"
            : "{$this->min_age}–{$this->max_age}"; // en dash U+2013
    }
}
