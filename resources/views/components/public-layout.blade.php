<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.become_seller') }} · VoiceCentra</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-brand-navy antialiased">
    <header class="absolute inset-x-0 top-0 z-30">
        <nav class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/voicecentra_icon_white.svg') }}" alt="VoiceCentra" class="h-7 w-7">
                <span class="text-white font-bold text-lg">VoiceCentra</span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1 text-blue-100">
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'text-white font-semibold' : 'hover:text-white' }}">EN</a>
                    <span class="opacity-40">/</span>
                    <a href="{{ route('locale.switch', 'es') }}" class="{{ app()->getLocale() === 'es' ? 'text-white font-semibold' : 'hover:text-white' }}">ES</a>
                </div>
                <a href="{{ route('login') }}" class="text-white/90 hover:text-white">{{ __('messages.log_in') }}</a>
                <a href="{{ route('register') }}" class="bg-brand-blue hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
                    {{ __('messages.become_seller') }}
                </a>
            </div>
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="bg-brand-navy text-blue-100/70 py-10">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/voicecentra_icon_white.svg') }}" alt="" class="h-6 w-6">
                <span class="text-white font-semibold">VoiceCentra</span>
            </div>
            <div>© {{ date('Y') }} VoiceCentra. {{ __('messages.footer_rights') }}</div>
        </div>
    </footer>
</body>
</html>
