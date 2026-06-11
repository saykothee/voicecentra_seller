<x-guest-layout>
    <div class="text-center">
        @if ($user->isRejected())
            <h1 class="text-2xl font-bold text-brand-navy">{{ __('messages.pending_rejected_title') }}</h1>
            <p class="mt-4 text-gray-600">{{ __('messages.pending_rejected_body') }}</p>
        @else
            <h1 class="text-2xl font-bold text-brand-navy">{{ __('messages.pending_title') }}</h1>
            <p class="mt-4 text-gray-600">{{ __('messages.pending_body') }}</p>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="text-sm text-brand-blue underline">
                {{ __('messages.log_out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
