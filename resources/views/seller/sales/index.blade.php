<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.my_sales') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.report_sale') }}</h3>
                <form method="POST" action="{{ route('seller.sales.store') }}" class="mt-4 grid gap-4 sm:grid-cols-4 items-end">
                    @csrf
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sold_at" :value="__('messages.sold_at_label')" />
                        <x-text-input id="sold_at" name="sold_at" type="date" max="{{ now()->toDateString() }}"
                                      class="mt-1 block w-full" :value="old('sold_at', now()->toDateString())" required />
                        <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="notes" :value="__('messages.notes')" />
                        <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                    <div>
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.submit_sale') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.sold_at_label') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sale_amount') }}</th>
                            <th class="px-4 py-3">{{ __('messages.notes') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                            <th class="px-4 py-3">{{ __('messages.submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->sold_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ \Illuminate\Support\Number::currency($sale->amount_cents / 100) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->notes }}</td>
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
                                <td class="px-4 py-3 text-gray-500">{{ $sale->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_sales') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
