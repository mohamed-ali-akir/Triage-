<x-layouts.guest title="Mot de passe oublié">
    <h1 class="mb-2 text-lg font-semibold">Mot de passe oublié</h1>
    <p class="mb-4 text-sm text-slate-600">
        Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.
    </p>

    @if (session('status'))
        <p role="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form.field name="email" label="Adresse e-mail">
            <x-form.input name="email" type="email" required autofocus autocomplete="username" />
        </x-form.field>

        <x-button class="w-full">Envoyer le lien</x-button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Retour à la connexion</a>
    </p>
</x-layouts.guest>
