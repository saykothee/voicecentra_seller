<?php

namespace App\Services;

use App\Models\MinSalesRequirement;
use App\Models\User;

class MinSalesLookup
{
    /**
     * Map a user to their age-based minimum-sales multiplier.
     *
     * @return array{age: ?int, label: ?string, min_sales: int, matched: bool}
     *   matched=false (no DOB, or age outside every bracket) → multiplier 1 so the
     *   member still shows the raw amount instead of zeroing out.
     */
    public function forUser(User $user): array
    {
        $age = $user->age; // null when date_of_birth is null

        $bracket = $age === null
            ? null
            : MinSalesRequirement::forAge($age)->first();

        if (! $bracket) {
            return ['age' => $age, 'label' => null, 'min_sales' => 1, 'matched' => false];
        }

        return [
            'age' => $age,
            'label' => $bracket->label(),
            'min_sales' => $bracket->min_sales,
            'matched' => true,
        ];
    }
}
