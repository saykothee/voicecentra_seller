<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.calculator_2_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.calculator_2_intro') }}</p>

                <form method="POST" action="{{ route('calculator2.compute') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="seller_id" :value="__('messages.select_seller')" />
                        <select id="seller_id" name="seller_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">—</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected((int) old('seller_id', $input['seller_id']) === $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('seller_id')" class="mt-2" />
                    </div>

                    @php $monthCount = max($monthsMin, min($monthsMax, (int) old('months', $months))); @endphp
                    <div>
                        <x-input-label for="months" :value="__('messages.months_to_project')" />
                        <x-text-input id="months" name="months" type="number" step="1" :min="$monthsMin" :max="$monthsMax"
                                      onchange="this.form.submit()"
                                      class="mt-1 block w-full" :value="old('months', $months)" required />
                        <x-input-error :messages="$errors->get('months')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-400">{{ __('messages.months_help', ['min' => $monthsMin, 'max' => $monthsMax]) }}</p>
                    </div>

                    {{-- Per-month sales: each month gets its own amount (per sale) and quantity. --}}
                    <div>
                        <x-input-label :value="__('messages.monthly_inputs')" />
                        <table class="mt-1 min-w-full text-sm">
                            <thead class="text-left text-gray-500">
                                <tr>
                                    <th class="py-1 pr-3 font-medium">{{ __('messages.col_month') }}</th>
                                    <th class="py-1 pr-3 font-medium">{{ __('messages.col_amount') }}</th>
                                    <th class="py-1 font-medium">{{ __('messages.col_quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($month = 1; $month <= $monthCount; $month++)
                                    <tr>
                                        <td class="py-1 pr-3 font-medium text-brand-navy whitespace-nowrap">{{ __('messages.month_n', ['n' => $month]) }}</td>
                                        <td class="py-1 pr-3">
                                            <x-text-input name="amount[{{ $month }}]" type="number" step="0.01" min="0.01"
                                                          class="block w-full text-sm" :value="old('amount.'.$month, $input['amounts'][$month] ?? 199)" required />
                                            <x-input-error :messages="$errors->get('amount.'.$month)" class="mt-1" />
                                        </td>
                                        <td class="py-1">
                                            <x-text-input name="quantity[{{ $month }}]" type="number" step="1" min="0" max="10000"
                                                          class="block w-full text-sm" :value="old('quantity.'.$month, $input['quantities'][$month] ?? 1)" required />
                                            <x-input-error :messages="$errors->get('quantity.'.$month)" class="mt-1" />
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm">
                        {{ __('messages.calculate') }}
                    </button>
                </form>

                {{-- Per-member breakdown: total 6-month volume × min_sales(age) --}}
                @if ($computed)
                    <p class="mt-8 text-sm font-medium text-brand-navy">{{ __('messages.total_volume_note', [
                        'total' => \Illuminate\Support\Number::currency($totalVolumeCents / 100),
                    ]) }}</p>
                    <h3 class="mt-4 font-semibold text-brand-navy">{{ __('messages.per_member_breakdown') }}</h3>
                    <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">{{ __('messages.col_member') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_name') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_age') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_bracket') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('messages.col_min_sales') }}</th>
                                <th class="py-2 text-right">{{ __('messages.col_effective') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($members as $m)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-brand-navy">{{ $m['level'] === 0 ? __('messages.seller_label') : 'L'.$m['level'] }}</td>
                                    <td class="py-2 pr-4">{{ $m['name'] }}</td>
                                    <td class="py-2 pr-4">{{ $m['age'] ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($m['matched'])
                                            {{ $m['label'] }}
                                        @else
                                            <span class="text-amber-600">{{ __('messages.no_requirement') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-right">×{{ $m['min_sales'] }}</td>
                                    <td class="py-2 text-right font-medium">{{ \Illuminate\Support\Number::currency($m['effective_cents'] / 100) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2">
                                <td class="py-2 pr-4 font-semibold text-brand-navy" colspan="5">{{ __('messages.effective_total') }}</td>
                                <td class="py-2 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($effectiveTotalCents / 100) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.projection_title', ['n' => $months]) }}</h3>

                @if (! $computed)
                    <p class="mt-4 text-sm text-gray-400">—</p>
                @else
                    @php
                        $sellerFlatTotalCents = array_sum(array_column($projection, 'seller_flat_cents'));
                        $sellerCommissionTotalCents = array_sum(array_column($projection, 'seller_commission_cents'));
                        $sellerTotalCents = array_sum(array_column($projection, 'seller_cents'));
                        $chainTotalCents = array_sum(array_column($projection, 'chain_cents'));
                        $grandTotalCents = array_sum(array_column($projection, 'total_cents'));
                    @endphp

                    <p class="mt-2 text-xs text-gray-500">{{ __('messages.ramp_note', ['flat' => \Illuminate\Support\Number::currency(config('commissions.ramp.seller_flat_cents') / 100)]) }}</p>

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th rowspan="2" class="py-2 pr-4 align-bottom">{{ __('messages.col_month') }}</th>
                                <th rowspan="2" class="py-2 pr-4 text-right align-bottom">{{ __('messages.col_active_volume') }}</th>
                                <th colspan="3" class="py-1 pr-4 text-center border-b border-gray-200 font-semibold text-brand-navy">{{ __('messages.seller_label') }}</th>
                                <th rowspan="2" class="py-2 pr-4 text-right align-bottom">{{ __('messages.col_chain') }}</th>
                                <th rowspan="2" class="py-2 text-right align-bottom">{{ __('messages.col_total') }}</th>
                            </tr>
                            <tr>
                                <th class="py-1 pr-4 text-right font-normal">{{ __('messages.col_flat') }}</th>
                                <th class="py-1 pr-4 text-right font-normal">{{ __('messages.col_commission') }}</th>
                                <th class="py-1 pr-4 text-right font-normal">{{ __('messages.col_total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($projection as $row)
                                <tr class="{{ $row['total_cents'] === 0 ? 'text-gray-400' : '' }}">
                                    <td class="py-2 pr-4 font-medium text-brand-navy">{{ __('messages.month_n', ['n' => $row['month']]) }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-500">{{ \Illuminate\Support\Number::currency($row['active_volume_cents'] / 100) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ \Illuminate\Support\Number::currency($row['seller_flat_cents'] / 100) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ \Illuminate\Support\Number::currency($row['seller_commission_cents'] / 100) }}</td>
                                    <td class="py-2 pr-4 text-right font-medium">{{ \Illuminate\Support\Number::currency($row['seller_cents'] / 100) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ \Illuminate\Support\Number::currency($row['chain_cents'] / 100) }}</td>
                                    <td class="py-2 text-right font-medium">{{ \Illuminate\Support\Number::currency($row['total_cents'] / 100) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2">
                                <td class="py-2 pr-4 font-semibold text-brand-navy">{{ __('messages.projection_total', ['n' => $months]) }}</td>
                                <td class="py-2 pr-4 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($totalVolumeCents / 100) }}</td>
                                <td class="py-2 pr-4 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($sellerFlatTotalCents / 100) }}</td>
                                <td class="py-2 pr-4 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($sellerCommissionTotalCents / 100) }}</td>
                                <td class="py-2 pr-4 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($sellerTotalCents / 100) }}</td>
                                <td class="py-2 pr-4 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($chainTotalCents / 100) }}</td>
                                <td class="py-2 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($grandTotalCents / 100) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
