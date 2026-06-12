<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">
            {{ __('messages.seller_dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-brand-navy">
                    {{ __('messages.welcome_name', ['name' => $user->name]) }}
                </h3>
                <p class="mt-2 text-gray-600">{{ __('messages.seller_welcome_body') }}</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">{{ __('messages.total_earned') }}</div>
                    <div class="text-3xl font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($totalEarnedCents / 100) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">{{ __('messages.earned_30d') }}</div>
                    <div class="text-3xl font-bold text-brand-blue">{{ \Illuminate\Support\Number::currency($earned30Cents / 100) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">{{ __('messages.pending_sales') }}</div>
                    <div class="text-3xl font-bold text-amber-500">{{ $pendingSalesCount }}</div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ copied: false }">
                <h4 class="font-semibold text-brand-navy">{{ __('messages.referral_link') }}</h4>
                <div class="mt-3 flex gap-2">
                    <input type="text" readonly value="{{ $referralLink }}"
                           class="flex-1 rounded-lg border-gray-300 text-sm text-gray-600 bg-gray-50">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $referralLink }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            class="bg-brand-blue hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        <span x-show="!copied">{{ __('messages.copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('messages.copied') }}</span>
                    </button>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h4 class="font-semibold text-brand-navy">{{ __('messages.recent_commissions') }}</h4>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($recentPayouts as $payout)
                        <li class="py-2 flex justify-between">
                            <span class="text-gray-600">
                                {{ $payout->level === 0 ? __('messages.your_sale') : 'L'.$payout->level.' · '.$payout->sale->seller->name }}
                            </span>
                            <span class="font-medium {{ $payout->status === 'paid' ? 'text-brand-navy' : 'text-gray-400 line-through' }}">
                                {{ \Illuminate\Support\Number::currency($payout->amount_cents / 100) }}
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-400">{{ __('messages.no_commissions') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="font-semibold text-brand-navy">{{ __('messages.your_profile') }}</h4>
                    <dl class="mt-3 text-sm text-gray-600 space-y-1">
                        <div><dt class="inline font-medium">{{ __('messages.name') }}:</dt> <dd class="inline">{{ $user->name }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('messages.email') }}:</dt> <dd class="inline">{{ $user->email }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('messages.phone') }}:</dt> <dd class="inline">{{ $user->phone }}</dd></div>
                    </dl>
                    <a href="{{ route('profile.edit') }}" class="mt-4 inline-block text-sm text-brand-blue underline">
                        {{ __('messages.edit_profile') }}
                    </a>
                </div>

                <div class="bg-brand-navy text-white shadow-sm sm:rounded-lg p-6 flex flex-col justify-center">
                    <h4 class="font-semibold">{{ __('messages.sales_tools') }}</h4>
                    <p class="mt-2 text-sm text-blue-100">{{ __('messages.sales_tools_soon') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
