@props(['status'])

@php
    use App\Enums\TicketStatus;

    $color = match ($status) {
        TicketStatus::New => 'sky',
        TicketStatus::PendingValidation => 'amber',
        TicketStatus::Assigned => 'indigo',
        TicketStatus::InProgress => 'violet',
        TicketStatus::WaitingUser => 'orange',
        TicketStatus::Resolved => 'emerald',
        TicketStatus::Closed => 'slate',
    };
@endphp

<x-badge :color="$color">{{ $status->label() }}</x-badge>
