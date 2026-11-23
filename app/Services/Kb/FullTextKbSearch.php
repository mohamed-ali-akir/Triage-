<?php

namespace App\Services\Kb;

use App\Models\KbArticle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Full-text search of MySQL and MariaDB, on the FULLTEXT index of kb_articles.
 * The database ranks the articles itself (MATCH ... AGAINST returns a relevance score).
 */
class FullTextKbSearch implements KbSearch
{
    public function search(string $query, int $limit = 5): Collection
    {
        if (trim($query) === '') {
            return new Collection;
        }

        return KbArticle::query()
            ->published()
            ->whereFullText(['title', 'content'], $query)
            // The query text is a bound parameter: it cannot inject SQL.
            ->orderByRaw('MATCH (title, content) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$query])
            ->limit($limit)
            ->get();
    }
}
