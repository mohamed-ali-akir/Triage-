<x-layouts.app title="Modifier une catégorie">
    <x-page-header title="Modifier une catégorie" />

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-form.field name="name" label="Nom">
                <x-form.input name="name" :value="$category->name" required maxlength="100" />
            </x-form.field>

            <x-form.field name="is_active" label="État" hint="Une catégorie désactivée n'est plus proposée pour les nouveaux tickets.">
                <x-form.select name="is_active">
                    <option value="1" @selected((string) old('is_active', (int) $category->is_active) === '1')>Active</option>
                    <option value="0" @selected((string) old('is_active', (int) $category->is_active) === '0')>Désactivée</option>
                </x-form.select>
            </x-form.field>

            <div class="flex gap-2">
                <x-button>Enregistrer</x-button>
                <x-button-link :href="route('admin.categories.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
