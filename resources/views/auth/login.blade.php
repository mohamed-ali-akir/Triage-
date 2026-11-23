<x-layouts.guest title="Connexion">
    <h1 class="mb-4 text-lg font-semibold">Connexion</h1>

    @if (session('status'))
        <p role="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-form.field name="email" label="Adresse e-mail">
            <x-form.input name="email" type="email" required autofocus autocomplete="username" />
        </x-form.field>

        <x-form.field name="password" label="Mot de passe">
            <x-form.input name="password" type="password" required autocomplete="current-password" />
        </x-form.field>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600">
                Se souvenir de moi
            </label>

            <a href="{{ route('password.request') }}" class="text-indigo-600 hover:underline">Mot de passe oublié ?</a>
        </div>

        <x-button class="w-full">Se connecter</x-button>
    </form>
</x-layouts.guest>
