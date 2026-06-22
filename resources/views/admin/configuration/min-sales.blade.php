<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.min_sales_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.min_sales_intro') }}</p>

                <form method="POST" action="{{ route('admin.configuration.min-sales.update') }}" class="mt-6">
                    @csrf @method('PATCH')

                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('messages.age_range') }}</th>
                                <th class="px-4 py-3">{{ __('messages.minimum_sales') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($requirements as $requirement)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-brand-navy">{{ $requirement->label() }}</td>
                                    <td class="px-4 py-3">
                                        <x-text-input type="number" min="0" step="1"
                                                      name="min_sales[{{ $requirement->id }}]"
                                                      :value="old('min_sales.'.$requirement->id, $requirement->min_sales)"
                                                      class="w-32" required />
                                        <x-input-error :messages="$errors->get('min_sales.'.$requirement->id)" class="mt-1" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-6">
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
