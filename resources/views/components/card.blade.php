@props(['title' => null])

<section {{ $attributes->class('rounded-lg border border-slate-200 bg-white p-6 shadow-sm') }}>
    @if ($title)
        <h2 class="mb-4 text-base font-semibold text-slate-900">{{ $title }}</h2>
    @endif

    {{ $slot }}
</section>
