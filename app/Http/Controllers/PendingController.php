<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PendingController extends Controller
{
    public function show(Request $request)
    {
        return view('pending', ['user' => $request->user()]);
    }
}
