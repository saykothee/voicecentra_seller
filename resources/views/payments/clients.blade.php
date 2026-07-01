<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.client_payments') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-6 text-sm text-gray-500">{{ __('messages.client_payments_intro') }}</p>

            {{-- Summary: how many clients in each state --}}
            <div class="mb-6 grid grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4 border-l-4 border-red-400">
                    <div class="text-2xl font-bold text-red-600">{{ $counts['late'] }}</div>
                    <div class="text-xs text-gray-500">{{ __('messages.pay_status_late') }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 border-l-4 border-amber-400">
                    <div class="text-2xl font-bold text-amber-600">{{ $counts['due_today'] }}</div>
                    <div class="text-xs text-gray-500">{{ __('messages.pay_status_due_today') }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 border-l-4 border-green-400">
                    <div class="text-2xl font-bold text-green-600">{{ $counts['to_be_paid'] }}</div>
                    <div class="text-xs text-gray-500">{{ __('messages.pay_status_to_be_paid') }}</div>
                </div>
            </div>

            {{-- Filter pills --}}
            <div class="mb-4 flex gap-2 text-sm">
                @foreach (['' => 'filter_all', 'late' => 'pay_status_late', 'due_today' => 'pay_status_due_today', 'to_be_paid' => 'pay_status_to_be_paid'] as $value => $label)
                    <a href="{{ route($filterRoute, $value ? ['status' => $value] : []) }}"
                       class="px-3 py-1.5 rounded-full {{ ($status ?? '') === $value ? 'bg-brand-blue text-white' : 'bg-gray-100 text-gray-600' }}">
                        {{ __('messages.'.$label) }}
                    </a>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.client_id') }}</th>
                            @if ($showSeller)
                                <th class="px-4 py-3">{{ __('messages.seller') }}</th>
                            @endif
                            <th class="px-4 py-3">{{ __('messages.col_billing_day') }}</th>
                            <th class="px-4 py-3">{{ __('messages.col_last_payment') }}</th>
                            <th class="px-4 py-3">{{ __('messages.col_next_due') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="px-4 py-3 font-medium text-brand-navy">
                                    {{ $client['client_id'] }}
                                    @if ($client['on_trial'])
                                        <span class="ml-1 rounded-full bg-indigo-100 text-indigo-700 px-2 py-0.5 text-xs">{{ __('messages.trial_badge') }}</span>
                                    @endif
                                </td>
                                @if ($showSeller)
                                    <td class="px-4 py-3 text-gray-600">{{ $client['seller']?->name ?? '—' }}</td>
                                @endif
                                <td class="px-4 py-3 text-gray-600">{{ $client['billing_day'] }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $client['last_payment_date']?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $client['next_due_date']->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-red-100 text-red-700' => $client['status'] === 'late',
                                            'bg-amber-100 text-amber-700' => $client['status'] === 'due_today',
                                            'bg-green-100 text-green-700' => $client['status'] === 'to_be_paid',
                                        ])">
                                        {{ __('messages.pay_status_'.$client['status']) }}
                                    </span>
                                    @if ($client['status'] === 'late')
                                        <span class="ml-1 text-xs text-gray-400">{{ __('messages.days_overdue', ['n' => $client['days_late']]) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $showSeller ? 6 : 5 }}" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_clients') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
