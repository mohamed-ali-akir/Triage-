<x-layouts.app title="Modifier un article">
    <x-page-header title="Modifier un article" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('admin.kb-articles.update', $article) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.kb._form')

            <div class="flex gap-2">
                <x-button>Enregistrer</x-button>
                <x-button-link :href="route('admin.kb-articles.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
