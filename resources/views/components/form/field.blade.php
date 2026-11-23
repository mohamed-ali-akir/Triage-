@props(['name', 'label', 'bag' => 'default', 'hint' => null])

{{-- A label, the form control passed as slot, an optional hint and the validation error. --}}
<div {{ $attributes->class('space-y-1') }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name, $bag)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
