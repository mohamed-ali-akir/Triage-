<x-layouts.app title="Nouvelle catégorie">
    <x-page-header title="Nouvelle catégorie" />

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-4">
            @csrf

            <x-form.field name="name" label="Nom">
                <x-form.input name="name" required maxlength="100" />
            </x-form.field>

            <div class="flex gap-2">
                <x-button>Créer</x-button>
                <x-button-link :href="route('admin.categories.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
