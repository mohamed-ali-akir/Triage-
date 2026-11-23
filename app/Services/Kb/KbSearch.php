<?php

namespace App\Services\Kb;

use App\Models\KbArticle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds the published articles that best match a free text
 * (a search typed by a technician, or the text of a ticket).
 */
interface KbSearch
{
    /**
     * @param  bool  $publicOnly  only the articles opened to the help center of the employees
     * @return Collection<int, KbArticle> most relevant first
     */
    public function search(string $query, int $limit = 5, bool $publicOnly = false): Collection;
}
