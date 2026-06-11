<x-public-layout>
    {{-- Hero --}}
    <section class="relative bg-brand-navy overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-navy via-brand-navy to-[#13205c]"></div>
        <div class="relative max-w-7xl mx-auto px-6 pt-36 pb-24">
            <p class="text-sm font-semibold tracking-widest text-blue-300 uppercase">{{ __('messages.hero_eyebrow') }}</p>
            <h1 class="mt-4 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight max-w-3xl">
                {{ __('messages.hero_title') }}
            </h1>
            <p class="mt-6 text-lg text-blue-100/80 max-w-2xl">{{ __('messages.hero_subtitle') }}</p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="{{ route('register') }}" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-7 py-3.5 rounded-xl">
                    {{ __('messages.hero_cta_primary') }} &rarr;
                </a>
                <a href="#how" class="border border-white/20 text-white px-7 py-3.5 rounded-xl hover:bg-white/5">
                    {{ __('messages.hero_cta_secondary') }}
                </a>
            </div>

            {{-- Voice waveform motif --}}
            <div class="mt-16 flex items-end gap-1.5 h-16 max-w-md" aria-hidden="true">
                @foreach ([40,75,55,100,65,90,45,80,35,70,50,95,60,85,42] as $h)
                    <div class="flex-1 rounded-sm
                        @class([
                            'bg-brand-blue' => $loop->index % 3 === 0,
                            'bg-blue-500' => $loop->index % 3 === 1,
                            'bg-sky-400' => $loop->index % 3 === 2,
                        ])"
                        style="height: {{ $h }}%"></div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-brand-navy text-center">{{ __('messages.how_title') }}</h2>
            <div class="mt-14 grid gap-8 md:grid-cols-3">
                @foreach (['1','2','3'] as $n)
                    <div class="text-center">
                        <div class="mx-auto w-12 h-12 rounded-full bg-brand-blue text-white flex items-center justify-center font-bold text-lg">{{ $n }}</div>
                        <h3 class="mt-5 font-semibold text-brand-navy text-lg">{{ __("messages.how_step{$n}_title") }}</h3>
                        <p class="mt-2 text-gray-500">{{ __("messages.how_step{$n}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- What you'll sell --}}
    <section class="bg-slate-50 py-24">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-brand-navy text-center">{{ __('messages.sell_title') }}</h2>
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach (['1','2','3'] as $n)
                    <div class="bg-white rounded-2xl p-7 shadow-sm">
                        <div class="w-11 h-11 rounded-xl bg-brand-navy/5 flex items-center justify-center text-brand-blue text-xl">●</div>
                        <h3 class="mt-5 font-semibold text-brand-navy text-lg">{{ __("messages.sell_feature{$n}_title") }}</h3>
                        <p class="mt-2 text-gray-500">{{ __("messages.sell_feature{$n}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Why sell with us --}}
    <section class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-brand-navy text-center">{{ __('messages.why_title') }}</h2>
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach (['1','2','3'] as $n)
                    <div class="border border-gray-100 rounded-2xl p-7">
                        <h3 class="font-semibold text-brand-navy text-lg">{{ __("messages.why_feature{$n}_title") }}</h3>
                        <p class="mt-2 text-gray-500">{{ __("messages.why_feature{$n}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="bg-brand-navy py-20">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-white">{{ __('messages.final_cta_title') }}</h2>
            <p class="mt-4 text-blue-100/80">{{ __('messages.final_cta_body') }}</p>
            <a href="{{ route('register') }}" class="mt-8 inline-block bg-brand-blue hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-xl">
                {{ __('messages.hero_cta_primary') }} &rarr;
            </a>
        </div>
    </section>
</x-public-layout>
