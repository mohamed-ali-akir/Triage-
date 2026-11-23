<x-layouts.app title="Articles">
    <x-page-header title="Articles de la base de connaissances" subtitle="Seuls les articles publiés sont proposés aux techniciens et utilisés par l'IA.">
        <x-button-link :href="route('admin.kb-articles.create')">Nouvel article</x-button-link>
    </x-page-header>

    @if ($articles->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">La base de connaissances est vide.</p>
        </x-card>
    @else
        <x-table>
            <x-slot:head>
                <th class="px-4 py-3">Titre</th>
                <th class="px-4 py-3">Catégorie</th>
                <th class="px-4 py-3">État</th>
                <th class="px-4 py-3">Modifié le</th>
                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @foreach ($articles as $article)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $article->title }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $article->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($article->is_published)
                            <x-badge color="emerald">Publié</x-badge>
                            <x-badge :color="$article->is_public ? 'indigo' : 'amber'">{{ $article->is_public ? 'Centre d\'aide' : 'Interne' }}</x-badge>
                        @else
                            <x-badge color="slate">Brouillon</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $article->updated_at->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('admin.kb-articles.edit', $article) }}" class="font-medium text-indigo-600 hover:underline">Modifier</a>

                            <form method="POST" action="{{ route('admin.kb-articles.destroy', $article) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:underline">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
</x-layouts.app>
