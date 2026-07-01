<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.create_sale') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-6">{{ __('messages.create_sale_intro') }}</p>

                <form method="POST" action="{{ route('admin.sales.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="seller_id" :value="__('messages.seller')" />
                        <select id="seller_id" name="seller_id" required
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            <option value="">{{ __('messages.select_seller') }}</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected(old('seller_id') == $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('seller_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="client_id" :value="__('messages.client_id')" />
                        <x-text-input id="client_id" name="client_id" type="text" class="mt-1 block w-full"
                                      :value="old('client_id')" required />
                        <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="sold_at" :value="__('messages.sold_at_label')" />
                            <x-text-input id="sold_at" name="sold_at" type="date" max="{{ now()->toDateString() }}"
                                          class="mt-1 block w-full" :value="old('sold_at', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="paid_at" :value="__('messages.paid_at_label')" />
                            <x-text-input id="paid_at" name="paid_at" type="date" max="{{ now()->toDateString() }}"
                                          class="mt-1 block w-full" :value="old('paid_at')" />
                            <x-input-error :messages="$errors->get('paid_at')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-6">
                        <label for="paid" class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input id="paid" name="paid" type="checkbox" value="1" @checked(old('paid'))
                                   class="rounded border-gray-300 text-brand-blue focus:ring-brand-blue">
                            {{ __('messages.mark_paid') }}
                        </label>
                        <label for="trial" class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input id="trial" name="trial" type="checkbox" value="1" @checked(old('trial'))
                                   class="rounded border-gray-300 text-brand-blue focus:ring-brand-blue">
                            {{ __('messages.free_trial') }}
                        </label>
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('messages.status')" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['pending', 'approved', 'rejected'] as $s)
                                <option value="{{ $s }}" @selected(old('status', 'pending') === $s)>{{ __('messages.status_'.$s) }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">{{ __('messages.create_sale_status_hint') }}</p>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('messages.notes')" />
                        <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.create_sale') }}
                        </button>
                        <a href="{{ route('admin.sales.index') }}" class="text-sm text-gray-500 underline">{{ __('messages.all_sales') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
