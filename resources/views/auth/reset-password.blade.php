<x-layouts.guest title="Nouveau mot de passe">
    <h1 class="mb-4 text-lg font-semibold">Choisir un nouveau mot de passe</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.field name="email" label="Adresse e-mail">
            <x-form.input name="email" type="email" :value="$request->email" required autocomplete="username" />
        </x-form.field>

        <x-form.field name="password" label="Nouveau mot de passe">
            <x-form.input name="password" type="password" required autocomplete="new-password" />
        </x-form.field>

        <x-form.field name="password_confirmation" label="Confirmation du mot de passe">
            <x-form.input name="password_confirmation" type="password" required autocomplete="new-password" />
        </x-form.field>

        <x-button class="w-full">Enregistrer</x-button>
    </form>
</x-layouts.guest>
