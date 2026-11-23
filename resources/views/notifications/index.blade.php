<x-layouts.app title="Notifications">
    <x-page-header title="Notifications">
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button variant="secondary">Tout marquer comme lu</x-button>
            </form>
        @endif
    </x-page-header>

    @if ($notifications->isEmpty())
        <x-card class="text-center">
            <p class="text-sm text-slate-600">Vous n'avez aucune notification.</p>
        </x-card>
    @else
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            @foreach ($notifications as $notification)
                <li>
                    <a href="{{ route('notifications.show', $notification->id) }}"
                       @class([
                           'flex items-center justify-between gap-4 px-4 py-3 text-sm hover:bg-slate-50',
                           'font-semibold text-slate-900' => $notification->unread(),
                           'text-slate-600' => $notification->read(),
                       ])>
                        <span class="flex items-center gap-2">
                            @if ($notification->unread())
                                <span class="h-2 w-2 shrink-0 rounded-full bg-indigo-600" aria-label="Non lue"></span>
                            @endif
                            {{ $notification->data['message'] }}
                        </span>
                        <span class="shrink-0 text-xs font-normal text-slate-500">{{ $notification->created_at->diffForHumans() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</x-layouts.app>
