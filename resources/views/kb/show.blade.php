<x-layouts.app :title="$article->title">
    <x-page-header :title="$article->title" :subtitle="'Mis à jour le '.$article->updated_at->format('d/m/Y')">
        @if ($article->category)
            <x-badge>{{ $article->category->name }}</x-badge>
        @endif
    </x-page-header>

    <x-card class="max-w-3xl">
        {{-- Safe to print as HTML: contentAsHtml() strips raw HTML and unsafe links. --}}
        <div class="kb-content">{!! $article->contentAsHtml() !!}</div>
    </x-card>

    <p class="mt-4 text-sm">
        <a href="{{ route('kb.index') }}" class="text-indigo-600 hover:underline">← Retour à la base de connaissances</a>
    </p>
</x-layouts.app>
