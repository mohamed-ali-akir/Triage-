<x-layouts.app title="Nouvel article">
    <x-page-header title="Nouvel article" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('admin.kb-articles.store') }}" class="space-y-4">
            @csrf

            @include('admin.kb._form')

            <div class="flex gap-2">
                <x-button>Créer l'article</x-button>
                <x-button-link :href="route('admin.kb-articles.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
