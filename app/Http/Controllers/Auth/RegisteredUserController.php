<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $sponsor = null;

        if ($request->filled('ref')) {
            $sponsor = User::where('referral_code', $request->query('ref'))
                ->where('role', 'seller')->where('status', 'approved')->first();
        }

        return view('auth.register', [
            'sponsor' => $sponsor,
            'ref' => $request->query('ref'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'ref' => ['nullable', 'string'],
        ]);

        $sponsor = null;

        if ($request->filled('ref')) {
            $sponsor = User::where('referral_code', $request->input('ref'))
                ->where('role', 'seller')->where('status', 'approved')->first();

            if (! $sponsor) {
                throw ValidationException::withMessages(['ref' => __('messages.invalid_ref')]);
            }

            if ($sponsor->depth >= (int) config('commissions.max_depth')) {
                throw ValidationException::withMessages(['ref' => __('messages.chain_full')]);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        if ($sponsor) {
            $user->parent_id = $sponsor->id;
            $user->depth = $sponsor->depth + 1;
            $user->save();
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
