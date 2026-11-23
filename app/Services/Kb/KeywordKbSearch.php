<?php

namespace App\Services\Kb;

use App\Models\KbArticle;
use App\Services\Text\Keywords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Fallback for databases without full-text search (SQLite, used by the tests):
 * counts the keywords shared by the query and each published article.
 * Simple and predictable, good enough for a few hundred articles.
 */
class KeywordKbSearch implements KbSearch
{
    // A keyword found in the title says more than one found in the body.
    private const TITLE_WEIGHT = 3;

    public function __construct(private readonly Keywords $keywords) {}

    public function search(string $query, int $limit = 5): Collection
    {
        $wanted = $this->keywords->extract($query);

        if ($wanted === []) {
            return new Collection;
        }

        $articles = KbArticle::query()->published()->get();

        $scores = [];

        foreach ($articles as $article) {
            $score = $this->score($article, $wanted);

            if ($score > 0) {
                $scores[$article->id] = $score;
            }
        }

        // Best score first; articles with the same score keep their original order.
        arsort($scores);
        $bestIds = array_slice(array_keys($scores), 0, $limit);

        return $articles
            ->filter(fn (KbArticle $article): bool => in_array($article->id, $bestIds, true))
            ->sortBy(fn (KbArticle $article): int|false => array_search($article->id, $bestIds, true))
            ->values();
    }

    /**
     * @param  list<string>  $wanted
     */
    private function score(KbArticle $article, array $wanted): int
    {
        $title = $this->keywords->extract($article->title);
        $content = $this->keywords->extract(Str::limit($article->content, 5000, ''));

        return count(array_intersect($wanted, $title)) * self::TITLE_WEIGHT
            + count(array_intersect($wanted, $content));
    }
}
