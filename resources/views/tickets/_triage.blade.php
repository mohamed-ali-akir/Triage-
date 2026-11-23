@can('triage', $ticket)
    <x-card title="Tri du ticket">
        <form method="POST" action="{{ route('tickets.triage.update', $ticket) }}" class="space-y-3">
            @csrf
            @method('PATCH')

            <x-form.field name="category_id" label="Catégorie">
                <x-form.select name="category_id" required>
                    <option value="">— Choisir —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $ticket->category_id) === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </x-form.select>
            </x-form.field>

            <x-form.field name="priority" label="Priorité">
                <x-form.select name="priority" required>
                    <option value="">— Choisir —</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', $ticket->priority?->value) === $priority->value)>
                            {{ $priority->label() }}
                        </option>
                    @endforeach
                </x-form.select>
            </x-form.field>

            <x-button variant="secondary" class="w-full">Enregistrer le tri</x-button>
        </form>
    </x-card>
@endcan
