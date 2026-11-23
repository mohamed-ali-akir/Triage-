<div class="space-y-1">
    <label for="title" class="block text-sm font-medium text-slate-700">Titre</label>

    {{-- A plain form field: the ticket is still sent by the surrounding form. --}}
    <input id="title" type="text" name="title" wire:model.live.debounce.400ms="title" required minlength="5" maxlength="150" autofocus
           class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">

    <p class="text-xs text-slate-500">Résumez le problème en une phrase.</p>

    @error('title')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if ($articles->isNotEmpty())
        <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50/70 p-4 text-sm" role="status">
            <p class="font-medium text-slate-900">Ces articles répondent peut-être déjà à votre question :</p>

            <ul class="mt-2 space-y-1">
                @foreach ($articles as $article)
                    <li wire:key="article-{{ $article->id }}">
                        <a href="{{ route('kb.show', $article) }}" target="_blank" rel="noopener" class="font-medium text-indigo-700 underline">{{ $article->title }}</a>
                    </li>
                @endforeach
            </ul>

            <p class="mt-2 text-xs text-slate-600">Si aucun ne convient, continuez : votre ticket sera traité normalement.</p>
        </div>
    @endif
</div>
