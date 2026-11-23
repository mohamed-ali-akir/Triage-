@props(['name', 'type' => 'text', 'value' => null])

<input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
       @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
       {{ $attributes->class('block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500') }}>
