@props(['name', 'value' => null, 'rows' => 6])

<textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
          {{ $attributes->class('block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500') }}>{{ old($name, $value) }}</textarea>
