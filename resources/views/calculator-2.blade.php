<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.calculator_2_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.calculator_2_intro') }}</p>

                <form method="POST" action="{{ route('calculator2.compute') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="seller_id" :value="__('messages.select_seller')" />
                        <select id="seller_id" name="seller_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">—</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected((int) old('seller_id', $input['seller_id']) === $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('seller_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount', $input['amount'])" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm">
                        {{ __('messages.calculate') }}
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.results') }}</h3>
                @if ($result === null)
                    <p class="mt-4 text-sm text-gray-400">—</p>
                @else
                    <p class="mt-4 text-sm text-gray-400">…</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
