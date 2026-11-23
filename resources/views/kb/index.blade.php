<x-layouts.app title="Base de connaissances">
    <x-page-header title="Base de connaissances" subtitle="Les procédures et solutions connues du support." />

    <form method="GET" action="{{ route('kb.index') }}" class="mb-6 flex gap-2">
        <label for="q" class="sr-only">Rechercher un article</label>
        <input id="q" type="search" name="q" value="{{ $query }}" placeholder="Rechercher : imprimante, VPN, mot de passe…"
               class="block w-full max-w-xl rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        <x-button>Rechercher</x-button>
    </form>

    @if ($articles->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">
                {{ $query === '' ? 'La base de connaissances est vide.' : 'Aucun article ne correspond à cette recherche.' }}
            </p>
        </x-card>
    @else
        <ul class="space-y-3">
            @foreach ($articles as $article)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('kb.show', $article) }}" class="font-medium text-indigo-700 hover:underline">{{ $article->title }}</a>

                    @if ($article->category)
                        <x-badge class="ml-2">{{ $article->category->name }}</x-badge>
                    @endif

                    <p class="mt-1 text-sm text-slate-600">{{ Str::limit(strip_tags($article->contentAsHtml()), 180) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
