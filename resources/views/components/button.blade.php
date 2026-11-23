@props(['variant' => 'primary', 'type' => 'submit'])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        default => 'bg-indigo-600 text-white hover:bg-indigo-700',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->class([
    'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium shadow-sm',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2',
    'disabled:cursor-not-allowed disabled:opacity-50',
    $classes,
]) }}>
    {{ $slot }}
</button>
