<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Services\Kb\KbSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only access to the knowledge base for the support staff.
 */
class KbController extends Controller
{
    public function index(Request $request, KbSearch $search): View
    {
        $query = trim((string) $request->string('q'));

        $articles = $query === ''
            ? KbArticle::query()->published()->with('category')->orderBy('title')->get()
            : $search->search($query, limit: 20)->load('category');

        return view('kb.index', ['articles' => $articles, 'query' => $query]);
    }

    public function show(KbArticle $kbArticle): View
    {
        // A draft does not exist for technicians.
        abort_unless($kbArticle->is_published, 404);

        return view('kb.show', ['article' => $kbArticle->load('category')]);
    }
}
