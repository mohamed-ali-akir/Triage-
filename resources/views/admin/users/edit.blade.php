<x-layouts.app title="Modifier un compte">
    <x-page-header title="Modifier un compte" :subtitle="$user->email" />

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.users._form')

            <x-form.field name="password" label="Nouveau mot de passe" hint="Laissez vide pour ne pas le changer.">
                <x-form.input name="password" type="password" autocomplete="new-password" />
            </x-form.field>

            <x-form.field name="is_active" label="État du compte" hint="Un compte désactivé ne peut plus se connecter. Son historique est conservé.">
                <x-form.select name="is_active">
                    <option value="1" @selected((string) old('is_active', (int) $user->is_active) === '1')>Actif</option>
                    <option value="0" @selected((string) old('is_active', (int) $user->is_active) === '0')>Désactivé</option>
                </x-form.select>
            </x-form.field>

            <div class="flex gap-2">
                <x-button>Enregistrer</x-button>
                <x-button-link :href="route('admin.users.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
