<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        @if (($sponsor ?? null) !== null)
            <div class="mb-4 rounded-lg bg-blue-50 text-brand-blue px-4 py-3 text-sm">
                {{ __('messages.sponsored_by', ['name' => $sponsor->name]) }}
            </div>
        @endif
        <input type="hidden" name="ref" value="{{ old('ref', $ref ?? '') }}">
        <x-input-error :messages="$errors->get('ref')" class="mb-4" />

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('messages.name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('messages.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Phone -->
        <div class="mt-4">
            <x-input-label for="phone" :value="__('messages.phone')" />
            <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone"
                          :value="old('phone')" required autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Date of birth -->
        <div class="mt-4">
            <x-input-label for="date_of_birth" :value="__('messages.date_of_birth')" />
            <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth"
                          :value="old('date_of_birth')" required
                          max="{{ now()->subYears(18)->toDateString() }}" />
            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('messages.password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('messages.confirm_password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('messages.already_registered') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('messages.register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
