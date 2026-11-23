@props(['priority'])

@php
    use App\Enums\TicketPriority;

    $color = match ($priority) {
        TicketPriority::Low => 'slate',
        TicketPriority::Normal => 'sky',
        TicketPriority::High => 'orange',
        TicketPriority::Critical => 'red',
        null => 'slate',
    };
@endphp

@if ($priority === null)
    <span class="text-xs text-slate-400">Non triée</span>
@else
    <x-badge :color="$color">{{ $priority->label() }}</x-badge>
@endif
