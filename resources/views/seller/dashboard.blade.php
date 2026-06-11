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
