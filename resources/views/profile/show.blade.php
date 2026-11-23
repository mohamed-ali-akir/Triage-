<x-layouts.app title="Mon profil">
    <x-page-header title="Mon profil" :subtitle="$user->email" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Changer de mot de passe">
            @if (session('status') === 'password-updated')
                <p role="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    Votre mot de passe a été modifié.
                </p>
            @endif

            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-form.field name="current_password" label="Mot de passe actuel" bag="updatePassword">
                    <x-form.input name="current_password" type="password" required autocomplete="current-password" />
                </x-form.field>

                <x-form.field name="password" label="Nouveau mot de passe" bag="updatePassword">
                    <x-form.input name="password" type="password" required autocomplete="new-password" />
                </x-form.field>

                <x-form.field name="password_confirmation" label="Confirmation" bag="updatePassword">
                    <x-form.input name="password_confirmation" type="password" required autocomplete="new-password" />
                </x-form.field>

                <x-button>Enregistrer</x-button>
            </form>
        </x-card>

        @include('profile._two-factor')
    </div>
</x-layouts.app>
