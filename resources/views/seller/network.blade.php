<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.my_network') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <div>
                    <span class="text-gray-500">{{ __('messages.your_sponsor') }}:</span>
                    <span class="font-semibold text-brand-navy">{{ $sponsor?->name ?? __('messages.top_level_seller') }}</span>
                </div>
                <div>
                    <span class="text-gray-500">{{ __('messages.downline') }}:</span>
                    <span class="font-semibold text-brand-navy">{{ $node['descendants_count'] }} {{ __('messages.members') }}</span>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($node['descendants_count'] === 0)
                    <p class="text-gray-400 text-sm">{{ __('messages.no_downline') }}</p>
                @endif
                @include('partials.seller-tree-node', ['node' => $node, 'counts' => $counts, 'rootDepth' => $node['user']->depth])
            </div>
        </div>
    </div>
</x-app-layout>
