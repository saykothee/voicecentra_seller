{{-- Params: $node (user/children/descendants_count), $counts (id => 90d sales), $rootDepth (int) --}}
@php
    $user = $node['user'];
    $sales90 = $counts[$user->id] ?? 0;
    $isActive = $sales90 >= (int) config('commissions.min_sales_quarter');
    $relLevel = $user->depth - $rootDepth;
@endphp
<div x-data="{ open: true }" class="mt-1">
    <div class="flex items-center gap-2 rounded-lg px-3 py-2 {{ $relLevel === 0 ? 'bg-blue-50' : 'bg-white border border-gray-100' }}">
        @if (count($node['children']) > 0)
            <button type="button" @click="open = !open" class="text-gray-400 w-4 text-left" x-text="open ? '▾' : '▸'"></button>
        @else
            <span class="w-4"></span>
        @endif
        <span class="w-7 h-7 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold shrink-0">
            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
        </span>
        <span class="font-medium text-brand-navy">{{ $user->name }}</span>
        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
            {{ $isActive ? __('messages.active') : __('messages.inactive') }}
        </span>
        <span class="ml-auto text-xs text-gray-500 whitespace-nowrap">
            L{{ $relLevel }} · {{ $sales90 }} {{ __('messages.sales_90d') }} · {{ $node['descendants_count'] }} {{ __('messages.downline') }}
        </span>
    </div>
    @if (count($node['children']) > 0)
        <div x-show="open" class="ml-5 pl-3 border-l-2 border-gray-100">
            @foreach ($node['children'] as $child)
                @include('partials.seller-tree-node', ['node' => $child, 'counts' => $counts, 'rootDepth' => $rootDepth])
            @endforeach
        </div>
    @endif
</div>
