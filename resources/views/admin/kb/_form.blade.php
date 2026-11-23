<x-form.field name="title" label="Titre">
    <x-form.input name="title" :value="$article->title" required minlength="5" maxlength="150" />
</x-form.field>

<x-form.field name="content" label="Contenu" hint="Format Markdown : # titre, - liste, 1. étapes, **gras**. Le HTML est retiré à l'affichage. N'écrivez aucun mot de passe ni secret : l'article est transmis à l'IA et peut être repris dans une réponse.">
    <x-form.textarea name="content" :value="$article->content" rows="14" required class="font-mono" />
</x-form.field>

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.field name="category_id" label="Catégorie">
        <x-form.select name="category_id">
            <option value="">— Aucune —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $article->category_id) === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </x-form.select>
    </x-form.field>

    <x-form.field name="is_published" label="État" hint="Un brouillon n'est ni recherché ni envoyé à l'IA.">
        <x-form.select name="is_published">
            <option value="0" @selected((string) old('is_published', (int) $article->is_published) === '0')>Brouillon</option>
            <option value="1" @selected((string) old('is_published', (int) $article->is_published) === '1')>Publié</option>
        </x-form.select>
    </x-form.field>
</div>
