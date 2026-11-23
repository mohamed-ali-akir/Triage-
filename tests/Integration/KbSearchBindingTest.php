<?php

use App\Services\Kb\FullTextKbSearch;
use App\Services\Kb\KbSearch;
use App\Services\Kb\KeywordKbSearch;
use Illuminate\Support\Facades\DB;

test('the search implementation follows the database driver', function () {
    $expected = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
        ? FullTextKbSearch::class
        : KeywordKbSearch::class;

    expect(app(KbSearch::class))->toBeInstanceOf($expected);
});
