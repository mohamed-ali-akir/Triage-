<x-card title="Double authentification">
    @if ($twoFactorEnabled)
        <p class="mb-4 text-sm text-slate-600">
            <x-badge color="emerald">Activée</x-badge>
            <span class="ml-1">Un code vous est demandé à chaque connexion.</span>
        </p>

        <details class="mb-4 rounded-md border border-slate-200 p-3 text-sm">
            <summary class="cursor-pointer font-medium text-slate-700">Afficher mes codes de secours</summary>

            <p class="mt-2 text-slate-600">
                Chaque code ne sert qu'une fois. Gardez-les en lieu sûr : ils permettent de se connecter sans le téléphone.
            </p>
            <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs text-slate-800">
                @foreach ($user->recoveryCodes() as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-3">
                @csrf
                <x-button variant="secondary">Générer de nouveaux codes</x-button>
            </form>
        </details>

        <form method="POST" action="{{ route('two-factor.disable') }}">
            @csrf
            @method('DELETE')
            <x-button variant="danger">Désactiver</x-button>
        </form>
    @elseif ($twoFactorPending)
        <p class="mb-3 text-sm text-slate-600">
            Scannez ce QR code avec une application d'authentification (Google Authenticator, Aegis, etc.),
            puis saisissez le code affiché pour terminer l'activation.
        </p>

        <div class="mb-3 inline-block rounded-md border border-slate-200 bg-white p-3">
            {!! $user->twoFactorQrCodeSvg() !!}
        </div>

        <p class="mb-4 text-xs text-slate-500">
            Clé à saisir à la main : <span class="font-mono text-slate-800">{{ decrypt($user->two_factor_secret) }}</span>
        </p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
            @csrf

            <x-form.field name="code" label="Code à 6 chiffres" bag="confirmTwoFactorAuthentication">
                <x-form.input name="code" inputmode="numeric" autocomplete="one-time-code" required />
            </x-form.field>

            <x-button>Terminer l'activation</x-button>
        </form>

        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-slate-500 hover:underline">Annuler</button>
        </form>
    @else
        <p class="mb-4 text-sm text-slate-600">
            En plus du mot de passe, un code à usage unique est demandé à la connexion.
            Recommandée pour les techniciens et les administrateurs.
        </p>

        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <x-button>Activer</x-button>
        </form>
    @endif
</x-card>
