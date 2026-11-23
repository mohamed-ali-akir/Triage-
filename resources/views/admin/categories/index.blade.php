<x-layouts.app title="Catégories">
    <x-page-header title="Catégories" subtitle="Les catégories servent à classer les tickets.">
        <x-button-link :href="route('admin.categories.create')">Nouvelle catégorie</x-button-link>
    </x-page-header>

    <x-table>
        <x-slot:head>
            <th class="px-4 py-3">Nom</th>
            <th class="px-4 py-3">Identifiant</th>
            <th class="px-4 py-3">État</th>
            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
        </x-slot:head>

        @foreach ($categories as $category)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-900">{{ $category->name }}</td>
                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $category->slug }}</td>
                <td class="px-4 py-3">
                    @if ($category->is_active)
                        <x-badge color="emerald">Active</x-badge>
                    @else
                        <x-badge color="red">Désactivée</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="font-medium text-indigo-600 hover:underline">Modifier</a>

                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="font-medium text-red-600 hover:underline">Supprimer</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>
</x-layouts.app>
