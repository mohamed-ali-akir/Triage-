<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Services\Kb\KbSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only access to the knowledge base. The support staff reads every published
 * article; an employee reads the help center, that is the articles opened to everyone.
 */
class KbController extends Controller
{
    public function index(Request $request, KbSearch $search): View
    {
        $user = $request->user();
        $query = trim((string) $request->string('q'));

        $articles = $query === ''
            ? KbArticle::query()->visibleTo($user)->with('category')->orderBy('title')->get()
            : $search->search($query, limit: 20, publicOnly: ! $user->isStaff())->load('category');

        return view('kb.index', ['articles' => $articles, 'query' => $query]);
    }

    public function show(Request $request, KbArticle $kbArticle): View
    {
        // A draft, or an internal article for an employee, does not exist: same 404,
        // so that the address of an article reveals nothing about what is hidden.
        abort_unless(KbArticle::query()->visibleTo($request->user())->whereKey($kbArticle->id)->exists(), 404);

        return view('kb.show', ['article' => $kbArticle->load('category')]);
    }
}
