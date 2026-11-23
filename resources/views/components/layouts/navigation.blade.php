<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight text-indigo-700">
            {{ config('app.name') }}
        </a>

        <nav class="flex flex-1 flex-wrap items-center gap-x-1 gap-y-1 text-sm" aria-label="Navigation principale">
            <x-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.index')">Mes tickets</x-nav-link>
            <x-nav-link :href="route('tickets.create')" :active="request()->routeIs('tickets.create')">Nouveau ticket</x-nav-link>

            @can('access-admin')
                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">Comptes</x-nav-link>
                <x-nav-link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')">Catégories</x-nav-link>
            @endcan
        </nav>

        <div class="flex items-center gap-3 text-sm">
            <x-nav-link :href="route('profile.show')" :active="request()->routeIs('profile.*')">
                {{ auth()->user()->name }}
            </x-nav-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md px-3 py-1.5 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                    Déconnexion
                </button>
            </form>
        </div>
    </div>
</header>
