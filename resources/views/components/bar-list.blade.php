@props(['rows', 'unit' => 'ticket'])

{{-- Horizontal bars on one baseline, one colour: the reader compares lengths. The figure sits at the tip of each bar. --}}
@php($max = max(1, collect($rows)->max('count')))

<ul class="viz space-y-2 text-sm">
    @foreach ($rows as $row)
        <li class="grid grid-cols-[minmax(7rem,10rem)_1fr] items-center gap-3" title="{{ $row['label'] }} : {{ $row['count'] }} {{ Str::plural($unit, $row['count']) }}">
            <span class="truncate text-slate-600">{{ $row['label'] }}</span>

            <span class="flex items-center gap-2">
                @if ($row['count'] > 0)
                    <span class="viz-bar" style="width: {{ round($row['count'] / $max * 85, 1) }}%"></span>
                @endif
                <span class="tabular-nums text-slate-900">{{ $row['count'] }}</span>
            </span>
        </li>
    @endforeach
</ul>
