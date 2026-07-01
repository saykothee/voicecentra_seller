<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard', 'admin.dashboard', 'seller.dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('admin.sellers.index')" :active="request()->routeIs('admin.sellers.*')">{{ __('messages.manage_sellers') }}</x-nav-link>
                        <x-nav-link :href="route('admin.network')" :active="request()->routeIs('admin.network')">{{ __('messages.network') }}</x-nav-link>
                        <x-nav-link :href="route('admin.sales.index')" :active="request()->routeIs('admin.sales.index')">{{ __('messages.all_sales') }}</x-nav-link>
                        <x-nav-link :href="route('admin.sales.create')" :active="request()->routeIs('admin.sales.create')">{{ __('messages.create_sale') }}</x-nav-link>
                        <x-nav-link :href="route('admin.bonus-pool')" :active="request()->routeIs('admin.bonus-pool')">{{ __('messages.bonus_pool') }}</x-nav-link>
                        <x-nav-link :href="route('admin.client-payments')" :active="request()->routeIs('admin.client-payments')">{{ __('messages.client_payments') }}</x-nav-link>
                        <x-nav-link :href="route('calculator')" :active="request()->routeIs('calculator')">{{ __('messages.calculator') }}</x-nav-link>
                        <x-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-nav-link>
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center h-full px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none
                                    {{ request()->routeIs('admin.configuration.*') ? 'border-brand-blue text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                    <div>{{ __('messages.configuration') }}</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('admin.configuration.min-sales')">
                                    {{ __('messages.min_sales_nav') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @elseif (Auth::user()->isSeller() && Auth::user()->isApproved())
                        <x-nav-link :href="route('seller.network')" :active="request()->routeIs('seller.network')">{{ __('messages.my_network') }}</x-nav-link>
                        <x-nav-link :href="route('seller.sales.index')" :active="request()->routeIs('seller.sales.*')">{{ __('messages.my_sales') }}</x-nav-link>
                        <x-nav-link :href="route('seller.commissions')" :active="request()->routeIs('seller.commissions')">{{ __('messages.my_commissions') }}</x-nav-link>
                        <x-nav-link :href="route('seller.client-payments')" :active="request()->routeIs('seller.client-payments')">{{ __('messages.client_payments') }}</x-nav-link>
                        <x-nav-link :href="route('calculator')" :active="request()->routeIs('calculator')">{{ __('messages.calculator') }}</x-nav-link>
                        <x-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Language Switcher -->
            <div class="hidden sm:flex sm:items-center sm:gap-1 sm:ms-6 text-sm text-gray-500">
                <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'text-brand-navy font-semibold' : 'hover:text-brand-navy' }}">EN</a>
                <span class="opacity-40">/</span>
                <a href="{{ route('locale.switch', 'es') }}" class="{{ app()->getLocale() === 'es' ? 'text-brand-navy font-semibold' : 'hover:text-brand-navy' }}">ES</a>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard', 'admin.dashboard', 'seller.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.sellers.index')" :active="request()->routeIs('admin.sellers.*')">{{ __('messages.manage_sellers') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.network')" :active="request()->routeIs('admin.network')">{{ __('messages.network') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.sales.index')" :active="request()->routeIs('admin.sales.index')">{{ __('messages.all_sales') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.sales.create')" :active="request()->routeIs('admin.sales.create')">{{ __('messages.create_sale') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.bonus-pool')" :active="request()->routeIs('admin.bonus-pool')">{{ __('messages.bonus_pool') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.client-payments')" :active="request()->routeIs('admin.client-payments')">{{ __('messages.client_payments') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('calculator')" :active="request()->routeIs('calculator')">{{ __('messages.calculator') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-responsive-nav-link>
                <div class="px-4 pt-2 text-xs font-semibold uppercase text-gray-400">{{ __('messages.configuration') }}</div>
                <x-responsive-nav-link :href="route('admin.configuration.min-sales')" :active="request()->routeIs('admin.configuration.min-sales')">
                    {{ __('messages.min_sales_nav') }}
                </x-responsive-nav-link>
            @elseif (Auth::user()->isSeller() && Auth::user()->isApproved())
                <x-responsive-nav-link :href="route('seller.network')" :active="request()->routeIs('seller.network')">{{ __('messages.my_network') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.sales.index')" :active="request()->routeIs('seller.sales.*')">{{ __('messages.my_sales') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.commissions')" :active="request()->routeIs('seller.commissions')">{{ __('messages.my_commissions') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('seller.client-payments')" :active="request()->routeIs('seller.client-payments')">{{ __('messages.client_payments') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('calculator')" :active="request()->routeIs('calculator')">{{ __('messages.calculator') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
