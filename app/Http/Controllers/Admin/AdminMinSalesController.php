<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinSalesRequirement;
use Illuminate\Http\Request;

class AdminMinSalesController extends Controller
{
    public function index()
    {
        $requirements = MinSalesRequirement::orderBy('min_age')->get();

        return view('admin.configuration.min-sales', compact('requirements'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'min_sales' => ['required', 'array'],
            'min_sales.*' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['min_sales'] as $id => $value) {
            MinSalesRequirement::where('id', $id)->update(['min_sales' => (int) $value]);
        }

        return redirect()->route('admin.configuration.min-sales')
            ->with('status', __('messages.min_sales_updated'));
    }
}
