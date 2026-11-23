@php($forStaff = auth()->user()->isStaff())

<x-layouts.app :title="$forStaff ? 'Base de connaissances' : 'Centre d\'aide'">
    @if ($forStaff)
        <x-page-header title="Base de connaissances" subtitle="Les procédures et solutions connues du support." />
    @else
        <x-page-header title="Centre d'aide" subtitle="Les réponses aux questions les plus fréquentes. Si vous ne trouvez pas, créez un ticket.">
            <x-button-link :href="route('tickets.create')">Nouveau ticket</x-button-link>
        </x-page-header>
    @endif

    <form method="GET" action="{{ route('kb.index') }}" class="mb-6 flex gap-2">
        <label for="q" class="sr-only">Rechercher un article</label>
        <input id="q" type="search" name="q" value="{{ $query }}" placeholder="Rechercher : imprimante, VPN, mot de passe…"
               class="block w-full max-w-xl rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        <x-button>Rechercher</x-button>
    </form>

    @if ($articles->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">
                {{ $query === '' ? 'Aucun article pour le moment.' : 'Aucun article ne correspond à cette recherche.' }}
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

                    @if ($forStaff && ! $article->is_public)
                        <x-badge color="amber" class="ml-1">Interne</x-badge>
                    @endif

                    <p class="mt-1 text-sm text-slate-600">{{ Str::limit(strip_tags($article->contentAsHtml()), 180) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
