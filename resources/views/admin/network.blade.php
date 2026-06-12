<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.network') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @forelse ($forest as $node)
                    @include('partials.seller-tree-node', ['node' => $node, 'counts' => $counts, 'rootDepth' => $node['user']->depth])
                @empty
                    <p class="text-gray-400 text-sm">{{ __('messages.no_sellers') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
