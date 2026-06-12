<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.bonus_pool') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-brand-navy text-white shadow-sm sm:rounded-lg p-6">
                <div class="text-sm text-blue-100/80">{{ __('messages.pool_balance') }}</div>
                <div class="text-4xl font-extrabold">{{ \Illuminate\Support\Number::currency($balanceCents / 100) }}</div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.registered') }}</th>
                            <th class="px-4 py-3">{{ __('messages.seller') }}</th>
                            <th class="px-4 py-3">{{ __('messages.level') }}</th>
                            <th class="px-4 py-3">{{ __('messages.reason') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('messages.sale_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->sale->seller->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->level !== null ? 'L'.$entry->level : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ __('messages.reason_'.$entry->reason) }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $entry->amount_cents < 0 ? 'text-red-600' : 'text-brand-navy' }}">
                                    {{ \Illuminate\Support\Number::currency($entry->amount_cents / 100) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $entries->links() }}</div>
        </div>
    </div>
</x-app-layout>
