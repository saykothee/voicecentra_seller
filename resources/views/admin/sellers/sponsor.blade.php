<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.change_sponsor') }} — {{ $seller->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    {{ __('messages.current_sponsor') }}:
                    <span class="font-semibold text-brand-navy">{{ $seller->parent?->name ?? __('messages.none') }}</span>
                </p>

                <form method="POST" action="{{ route('admin.sellers.sponsor.update', $seller) }}" class="mt-6 space-y-4">
                    @csrf @method('PATCH')
                    <div>
                        <x-input-label for="sponsor_email" :value="__('messages.new_sponsor_email')" />
                        <x-text-input id="sponsor_email" name="sponsor_email" type="email" class="mt-1 block w-full"
                                      :value="old('sponsor_email', $seller->parent?->email)" />
                        <x-input-error :messages="$errors->get('sponsor_email')" class="mt-2" />
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                        {{ __('messages.save') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
