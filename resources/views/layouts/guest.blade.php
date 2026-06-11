<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VoiceCentra</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen flex flex-col justify-center items-center bg-brand-navy py-10">
        <a href="{{ route('landing') }}" class="flex items-center gap-2 mb-6">
            <img src="{{ asset('images/voicecentra_icon_white.svg') }}" alt="VoiceCentra" class="h-9 w-9">
            <span class="text-white font-bold text-2xl">VoiceCentra</span>
        </a>

        <div class="w-full sm:max-w-md px-8 py-7 bg-white shadow-xl rounded-2xl">
            {{ $slot }}
        </div>

        <div class="mt-6 text-sm text-blue-100/70 flex items-center gap-1">
            <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'text-white font-semibold' : 'hover:text-white' }}">EN</a>
            <span class="opacity-40">/</span>
            <a href="{{ route('locale.switch', 'es') }}" class="{{ app()->getLocale() === 'es' ? 'text-white font-semibold' : 'hover:text-white' }}">ES</a>
        </div>
    </div>
</body>
</html>
