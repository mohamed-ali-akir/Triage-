@props(['href', 'active' => false])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'rounded-md px-3 py-1.5 font-medium',
       'bg-indigo-50 text-indigo-700' => $active,
       'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
   ]) }}>
    {{ $slot }}
</a>
