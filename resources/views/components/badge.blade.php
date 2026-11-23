@props(['color' => 'slate'])

@php
    // Full class names are written out so that Tailwind can detect them.
    $classes = match ($color) {
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'orange' => 'bg-orange-50 text-orange-800 ring-orange-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    {{ $slot }}
</span>
