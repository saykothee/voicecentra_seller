<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.my_commissions') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-sm text-gray-500">{{ __('messages.total_earned') }}</div>
                <div class="text-3xl font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($totalCents / 100) }}</div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.registered') }}</th>
                            <th class="px-4 py-3">{{ __('messages.level') }}</th>
                            <th class="px-4 py-3">{{ __('messages.from_seller') }}</th>
                            <th class="px-4 py-3">{{ __('messages.rate') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sale_amount') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payouts as $payout)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $payout->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">{{ $payout->level === 0 ? __('messages.your_sale') : 'L'.$payout->level }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $payout->sale->seller->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ rtrim(rtrim(number_format($payout->rate_numerator / 5120 * 100, 8), '0'), '.') }}%</td>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ \Illuminate\Support\Number::currency($payout->amount_cents / 100) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $payout->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                                        {{ __('messages.status_'.$payout->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_commissions') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $payouts->links() }}</div>
        </div>
    </div>
</x-app-layout>
