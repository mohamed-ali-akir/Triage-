<x-layouts.app title="Nouveau ticket">
    <x-page-header title="Nouveau ticket" subtitle="Décrivez votre problème : le support se charge de le classer et de le prioriser." />

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            {{-- The title field suggests help-center articles while the employee types. --}}
            <livewire:ticket-title-field :title="old('title', '')" />

            <x-form.field name="description" label="Description" hint="Que se passe-t-il ? Depuis quand ? Sur quel poste ou quel logiciel ?">
                <x-form.textarea name="description" required minlength="10" maxlength="5000" />
            </x-form.field>

            <div class="space-y-1">
                <label for="attachments" class="block text-sm font-medium text-slate-700">Pièces jointes (facultatif)</label>
                <input id="attachments" type="file" name="attachments[]" multiple
                       accept="{{ collect(config('tickets.attachments.extensions'))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
                       class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700">
                <p class="text-xs text-slate-500">
                    Une capture d'écran de l'erreur aide beaucoup. {{ config('tickets.attachments.max_files_per_upload') }} fichiers au plus,
                    {{ (int) (config('tickets.attachments.max_size_kb') / 1024) }} Mo chacun : {{ implode(', ', (array) config('tickets.attachments.extensions')) }}.
                </p>

                @error('attachments')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                N'écrivez jamais de mot de passe dans un ticket : le support ne vous le demandera pas. Un mot de passe écrit par erreur est retiré à l'enregistrement.
            </p>

            <div class="flex gap-2">
                <x-button>Envoyer le ticket</x-button>
                <x-button-link :href="route('tickets.index')" variant="secondary">Annuler</x-button-link>
            </div>
        </form>
    </x-card>
</x-layouts.app>
