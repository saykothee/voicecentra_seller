<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.edit_profile') }} — {{ $seller->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.sellers.update', $seller) }}" class="space-y-5">
                    @csrf @method('PATCH')

                    <div>
                        <x-input-label for="name" :value="__('messages.name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $seller->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('messages.email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                      :value="old('email', $seller->email)" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('messages.phone')" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                                      :value="old('phone', $seller->phone)" required />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div x-data="{
                            dob: '{{ old('date_of_birth', optional($seller->date_of_birth)->format('Y-m-d')) }}',
                            get age() {
                                if (! this.dob) return '';
                                const b = new Date(this.dob), t = new Date();
                                let a = t.getFullYear() - b.getFullYear();
                                const m = t.getMonth() - b.getMonth();
                                if (m < 0 || (m === 0 && t.getDate() < b.getDate())) a--;
                                return a >= 0 ? a : '';
                            }
                         }" class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <x-input-label for="date_of_birth" :value="__('messages.date_of_birth')" />
                            <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                                          x-model="dob" max="{{ now()->subYears(18)->toDateString() }}" />
                            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="age" :value="__('messages.age')" />
                            <x-text-input id="age" type="text" class="mt-1 block w-full bg-gray-100"
                                          disabled x-bind:value="age" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('messages.status')" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['pending', 'approved', 'rejected'] as $s)
                                <option value="{{ $s }}" @selected(old('status', $seller->status) === $s)>{{ __('messages.status_'.$s) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('messages.role')" />
                        <select id="role" name="role" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            <option value="seller" @selected(old('role', $seller->role) === 'seller')>{{ __('messages.role_seller') }}</option>
                            <option value="admin" @selected(old('role', $seller->role) === 'admin')>{{ __('messages.role_admin') }}</option>
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.save') }}
                        </button>
                        <a href="{{ route('admin.sellers.index') }}" class="text-sm text-gray-500 underline">{{ __('messages.manage_sellers') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
