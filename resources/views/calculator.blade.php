<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.calculator_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.calculator_intro') }}</p>

                <form method="POST" action="{{ route('calculator.compute') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount', $input['amount'])" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="uplines" :value="__('messages.uplines_count')" />
                        <select id="uplines" name="uplines" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            @foreach (range(0, 9) as $n)
                                <option value="{{ $n }}" @selected((int) old('uplines', $input['uplines']) === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-2">{{ __('messages.active_levels_hint') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (range(4, 9) as $level)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="hidden" name="active[{{ $level }}]" value="0">
                                    <input type="checkbox" name="active[{{ $level }}]" value="1"
                                           class="rounded border-gray-300 text-brand-blue"
                                           @checked(old("active.$level", $input['active'][$level] ?? false))>
                                    {{ __('messages.level_active', ['n' => $level]) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm">
                        {{ __('messages.calculate') }}
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.results') }}</h3>

                @if ($result === null)
                    <p class="mt-4 text-sm text-gray-400">—</p>
                @else
                    @php $uplinesPaid = collect($result['levels'])->where('paid', true)->sum('amount_cents'); @endphp

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">{{ __('messages.level') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.rate') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('messages.sale_amount') }}</th>
                                <th class="py-2">{{ __('messages.destination') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-2 pr-4 font-medium text-brand-navy">{{ __('messages.seller_cut') }}</td>
                                <td class="py-2 pr-4">10%</td>
                                <td class="py-2 pr-4 text-right font-medium">{{ \Illuminate\Support\Number::currency($result['seller_cents'] / 100) }}</td>
                                <td class="py-2 text-green-700">{{ __('messages.dest_paid') }}</td>
                            </tr>
                            @foreach ($result['levels'] as $level => $line)
                                <tr class="{{ $line['exists'] ? '' : 'text-gray-400' }}">
                                    <td class="py-2 pr-4">L{{ $level }}</td>
                                    <td class="py-2 pr-4">{{ rtrim(rtrim(number_format(config('commissions.level_numerators')[$level] / 5120 * 100, 8), '0'), '.') }}%</td>
                                    <td class="py-2 pr-4 text-right">{{ \Illuminate\Support\Number::currency($line['amount_cents'] / 100) }}</td>
                                    <td class="py-2">
                                        @if ($line['paid'])
                                            <span class="text-green-700">{{ __('messages.dest_paid') }}</span>
                                        @elseif ($line['pool_reason'] === 'inactive_upline')
                                            <span class="text-amber-600">{{ __('messages.dest_pool_inactive') }}</span>
                                        @else
                                            <span class="text-gray-500">{{ __('messages.dest_pool_no_upline') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="py-2 pr-4 text-gray-500" colspan="2">{{ __('messages.rounding_remainder') }}</td>
                                <td class="py-2 pr-4 text-right text-gray-500">{{ \Illuminate\Support\Number::currency($result['pool_rounding_cents'] / 100) }}</td>
                                <td class="py-2 text-gray-500">{{ __('messages.pool_total') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                        <dt class="text-gray-500">{{ __('messages.seller_cut') }}</dt>
                        <dd class="text-right font-semibold text-brand-navy">{{ \Illuminate\Support\Number::currency($result['seller_cents'] / 100) }}</dd>
                        <dt class="text-gray-500">{{ __('messages.uplines_total') }}</dt>
                        <dd class="text-right font-semibold text-brand-navy">{{ \Illuminate\Support\Number::currency($uplinesPaid / 100) }}</dd>
                        <dt class="text-gray-500">{{ __('messages.pool_total') }}</dt>
                        <dd class="text-right font-semibold text-emerald-600">{{ \Illuminate\Support\Number::currency($result['pool_total_cents'] / 100) }}</dd>
                        <dt class="text-gray-500 border-t pt-2">{{ __('messages.company_cost') }} (19.98046875%)</dt>
                        <dd class="text-right font-bold text-brand-navy border-t pt-2">{{ \Illuminate\Support\Number::currency($result['total_charge_cents'] / 100) }}</dd>
                    </dl>

                    <p class="mt-4 text-xs text-green-700">✓ {{ __('messages.invariant_ok') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
