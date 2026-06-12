<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizeAccess($request->user());

        return view('calculator', [
            'result' => null,
            'input' => ['amount' => 1000, 'uplines' => 9, 'active' => [4 => true, 5 => true, 6 => true, 7 => true, 8 => true, 9 => true]],
        ]);
    }

    public function compute(Request $request, CommissionCalculator $calculator)
    {
        $this->authorizeAccess($request->user());

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'uplines' => ['required', 'integer', 'min:0', 'max:9'],
            'active' => ['sometimes', 'array'],
            'active.*' => ['boolean'],
        ]);

        $autoLevels = (int) config('commissions.auto_levels');
        $slots = [];
        for ($level = 1; $level <= (int) $data['uplines']; $level++) {
            $slots[$level] = $level <= $autoLevels || (bool) ($data['active'][$level] ?? false);
        }

        $result = $calculator->calculate((int) round($data['amount'] * 100), $slots);

        return view('calculator', [
            'result' => $result,
            'input' => [
                'amount' => $data['amount'],
                'uplines' => (int) $data['uplines'],
                'active' => collect(range(4, 9))->mapWithKeys(fn ($l) => [$l => (bool) ($data['active'][$l] ?? false)])->all(),
            ],
        ]);
    }

    private function authorizeAccess(User $user): void
    {
        abort_unless($user->isAdmin() || ($user->isSeller() && $user->isApproved()), 403);
    }
}
