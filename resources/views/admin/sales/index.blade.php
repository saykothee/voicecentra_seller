<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.all_sales') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex gap-2 text-sm">
                @foreach (['' => 'all', 'pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected', 'refunded' => 'refunded'] as $value => $label)
                    <a href="{{ route('admin.sales.index', $value ? ['status' => $value] : []) }}"
                       class="px-3 py-1.5 rounded-full {{ ($status ?? '') === $value ? 'bg-brand-blue text-white' : 'bg-gray-100 text-gray-600' }}">
                        {{ __('messages.filter_'.$label) }}
                    </a>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.seller') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sale_amount') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sold_at_label') }}</th>
                            <th class="px-4 py-3">{{ __('messages.notes') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ $sale->seller->name }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Number::currency($sale->amount_cents / 100) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->sold_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Str::limit($sale->notes, 40) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-amber-100 text-amber-700' => $sale->status === 'pending',
                                            'bg-green-100 text-green-700' => $sale->status === 'approved',
                                            'bg-red-100 text-red-700' => $sale->status === 'rejected',
                                            'bg-gray-200 text-gray-600' => $sale->status === 'refunded',
                                        ])">
                                        {{ __('messages.status_'.$sale->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($sale->status === 'pending')
                                            <form method="POST" action="{{ route('admin.sales.approve', $sale) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-brand-blue font-medium">{{ __('messages.approve') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.sales.reject', $sale) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-red-600 font-medium">{{ __('messages.reject') }}</button>
                                            </form>
                                        @elseif ($sale->status === 'approved')
                                            <form method="POST" action="{{ route('admin.sales.refund', $sale) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-red-600 font-medium">{{ __('messages.refund') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_sales') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
