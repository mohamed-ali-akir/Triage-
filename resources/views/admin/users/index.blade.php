<x-layouts.app title="Comptes">
    <x-page-header title="Comptes" subtitle="Les comptes sont créés par un administrateur : il n'y a pas d'inscription publique.">
        <x-button-link :href="route('admin.users.create')">Nouveau compte</x-button-link>
    </x-page-header>

    <x-table>
        <x-slot:head>
            <th class="px-4 py-3">Nom</th>
            <th class="px-4 py-3">Adresse e-mail</th>
            <th class="px-4 py-3">Rôle</th>
            <th class="px-4 py-3">Service</th>
            <th class="px-4 py-3">État</th>
            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
        </x-slot:head>

        @foreach ($users as $user)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-900">{{ $user->name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                <td class="px-4 py-3">
                    <x-badge :color="$user->isAdmin() ? 'violet' : ($user->isTechnician() ? 'indigo' : 'slate')">
                        {{ $user->role->label() }}
                    </x-badge>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ $user->department?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($user->is_active)
                        <x-badge color="emerald">Actif</x-badge>
                    @else
                        <x-badge color="red">Désactivé</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-indigo-600 hover:underline">Modifier</a>
                </td>
            </tr>
        @endforeach
    </x-table>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
