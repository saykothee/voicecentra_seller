<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">
            {{ __('messages.admin_dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-brand-navy">{{ $stats['total'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.total_sellers') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-amber-500">{{ $stats['pending'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.pending_sellers') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-brand-blue">{{ $stats['approved'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.approved_sellers') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-red-500">{{ $stats['rejected'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.rejected_sellers') }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-amber-500">{{ $salesStats['pending_sales'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.pending_sales') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($salesStats['volume_cents'] / 100) }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.sales_volume') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-brand-blue">{{ \Illuminate\Support\Number::currency($salesStats['paid_cents'] / 100) }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.commissions_paid') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-emerald-600">{{ \Illuminate\Support\Number::currency($salesStats['pool_cents'] / 100) }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.pool_balance') }}</div>
                </div>
            </div>

            <a href="{{ route('admin.sellers.index') }}"
               class="mt-8 inline-block bg-brand-blue text-white px-5 py-2.5 rounded-lg text-sm font-semibold">
                {{ __('messages.manage_sellers') }}
            </a>
        </div>
    </div>
</x-app-layout>
