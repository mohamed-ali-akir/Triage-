<x-layouts.app title="Nouveau compte">
    <x-page-header title="Nouveau compte" />

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf

            @include('admin.users._form')

            <x-form.field name="password" label="Mot de passe initial" hint="12 caractères minimum. L'utilisateur pourra le changer depuis son profil.">
                <x-form.input name="password" type="password" required autocomplete="new-password" />
            </x-form.field>

            <div class="flex gap-2">
                <x-button>Créer le compte</x-button>
                <x-button-link :href="route('admin.users.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
