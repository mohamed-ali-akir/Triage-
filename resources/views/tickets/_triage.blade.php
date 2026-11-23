@can('triage', $ticket)
    @php
        // An untriaged ticket with a suggestion: the form starts from what the AI proposed,
        // so that correcting it is a matter of changing one field.
        $suggestion = $ticket->pendingSuggestion;
        $defaultCategoryId = $ticket->category_id ?? $suggestion?->category_id;
        $defaultPriority = $ticket->priority?->value ?? $suggestion?->priority?->value;
    @endphp

    <x-card title="Tri du ticket">
        <form method="POST" action="{{ route('tickets.triage.update', $ticket) }}" class="space-y-3">
            @csrf
            @method('PATCH')

            <x-form.field name="category_id" label="Catégorie">
                <x-form.select name="category_id" required>
                    <option value="">— Choisir —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $defaultCategoryId) === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </x-form.select>
            </x-form.field>

            <x-form.field name="priority" label="Priorité">
                <x-form.select name="priority" required>
                    <option value="">— Choisir —</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', $defaultPriority) === $priority->value)>
                            {{ $priority->label() }}
                        </option>
                    @endforeach
                </x-form.select>
            </x-form.field>

            <x-button variant="secondary" class="w-full">Enregistrer le tri</x-button>
        </form>
    </x-card>
@endcan
