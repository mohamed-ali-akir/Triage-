<?php

namespace App\Models;

use Database\Factories\KbArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['title', 'content', 'category_id', 'is_published', 'is_public'])]
class KbArticle extends Model
{
    /** @use HasFactory<KbArticleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
        'is_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<KbArticle>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * What a person may read: the support staff reads every published article,
     * an employee reads only those opened to the help center.
     *
     * @param  Builder<KbArticle>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('is_published', true);

        if (! $user->isStaff()) {
            $query->where('is_public', true);
        }
    }

    /**
     * The content is written in Markdown. Raw HTML is stripped and unsafe links
     * are refused, so an article cannot inject a script into the page.
     */
    public function contentAsHtml(): string
    {
        return Str::markdown($this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
