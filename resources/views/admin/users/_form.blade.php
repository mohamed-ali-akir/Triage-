{{-- Fields shared by the create and edit screens. --}}
<x-form.field name="name" label="Nom">
    <x-form.input name="name" :value="$user->name" required maxlength="255" />
</x-form.field>

<x-form.field name="email" label="Adresse e-mail">
    <x-form.input name="email" type="email" :value="$user->email" required maxlength="255" />
</x-form.field>

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.field name="role" label="Rôle">
        <x-form.select name="role" required>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>
                    {{ $role->label() }}
                </option>
            @endforeach
        </x-form.select>
    </x-form.field>

    <x-form.field name="department_id" label="Service">
        <x-form.select name="department_id">
            <option value="">— Aucun —</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $user->department_id) === (string) $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </x-form.select>
    </x-form.field>
</div>
