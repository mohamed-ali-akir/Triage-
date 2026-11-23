<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KbArticleRequest;
use App\Models\Category;
use App\Models\KbArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KbArticleController extends Controller
{
    public function index(): View
    {
        return view('admin.kb.index', [
            'articles' => KbArticle::query()->with('category')->orderBy('title')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.kb.create', [
            'article' => new KbArticle,
            'categories' => Category::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(KbArticleRequest $request): RedirectResponse
    {
        KbArticle::create($request->validated());

        return redirect()->route('admin.kb-articles.index')->with('success', 'L\'article a été créé.');
    }

    public function edit(KbArticle $kbArticle): View
    {
        return view('admin.kb.edit', [
            'article' => $kbArticle,
            'categories' => Category::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(KbArticleRequest $request, KbArticle $kbArticle): RedirectResponse
    {
        $kbArticle->update($request->validated());

        return redirect()->route('admin.kb-articles.index')->with('success', 'L\'article a été mis à jour.');
    }

    public function destroy(KbArticle $kbArticle): RedirectResponse
    {
        $kbArticle->delete();

        return redirect()->route('admin.kb-articles.index')->with('success', 'L\'article a été supprimé.');
    }
}
