<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PendingController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isApproved()) {
            return redirect()->route('seller.dashboard');
        }

        return view('pending', ['user' => $user]);
    }
}
