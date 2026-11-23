<x-layouts.guest title="Confirmation">
    <h1 class="mb-2 text-lg font-semibold">Confirmez votre mot de passe</h1>
    <p class="mb-4 text-sm text-slate-600">
        Cette action est sensible : saisissez votre mot de passe pour continuer.
    </p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf

        <x-form.field name="password" label="Mot de passe">
            <x-form.input name="password" type="password" required autofocus autocomplete="current-password" />
        </x-form.field>

        <x-button class="w-full">Confirmer</x-button>
    </form>
</x-layouts.guest>
