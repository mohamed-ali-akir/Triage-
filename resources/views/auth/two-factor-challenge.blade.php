<x-layouts.guest title="Double authentification">
    <h1 class="mb-2 text-lg font-semibold">Double authentification</h1>
    <p class="mb-4 text-sm text-slate-600">
        Saisissez le code à 6 chiffres affiché par votre application d'authentification.
    </p>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
        @csrf

        <x-form.field name="code" label="Code">
            <x-form.input name="code" inputmode="numeric" autocomplete="one-time-code" autofocus />
        </x-form.field>

        <details class="text-sm">
            <summary class="cursor-pointer text-indigo-600">Utiliser un code de secours</summary>

            <x-form.field name="recovery_code" label="Code de secours" class="mt-3">
                <x-form.input name="recovery_code" autocomplete="off" />
            </x-form.field>
        </details>

        <x-button class="w-full">Valider</x-button>
    </form>
</x-layouts.guest>
