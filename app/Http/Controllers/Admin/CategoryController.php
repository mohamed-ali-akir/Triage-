<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', ['category' => new Category]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $slug = Str::slug($request->validated('name'));

        // Two different names can produce the same slug ("Accès" and "Acces").
        if (Category::query()->where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'Une catégorie au nom très proche existe déjà.']);
        }

        Category::create([...$request->validated(), 'slug' => $slug]);

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été créée.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        // The slug is deliberately left untouched: it is a stable identifier.
        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été mise à jour.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Deleting a category in use would leave tickets without a category: deactivate it instead.
        if ($category->tickets()->exists()) {
            return back()->with('error', 'Cette catégorie est utilisée par des tickets. Désactivez-la plutôt que de la supprimer.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été supprimée.');
    }
}
