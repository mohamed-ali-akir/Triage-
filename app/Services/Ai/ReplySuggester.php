<?php

namespace App\Services\Ai;

use App\Enums\ReplyStatus;
use App\Models\AiSuggestion;
use App\Models\KbArticle;
use App\Services\Kb\KbSearch;
use App\Services\Privacy\PiiMasker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Drafts a reply for the technician from the knowledge base (a simple "RAG"):
 *  1. retrieve the published articles that match the ticket;
 *  2. give them to the AI with the ticket;
 *  3. keep the draft together with the articles it cites.
 *
 * Without a relevant article there is no draft: the AI must not invent a procedure.
 */
class ReplySuggester
{
    private const MAX_ARTICLES = 3;

    // Characters of one article sent to the AI: enough for a procedure, bounded for the cost.
    private const MAX_ARTICLE_LENGTH = 4000;

    public function __construct(
        private readonly KbSearch $search,
        private readonly AiClient $ai,
        private readonly PromptRepository $prompts,
        private readonly TicketPromptFormatter $formatter,
        private readonly PiiMasker $masker,
    ) {}

    public function suggest(AiSuggestion $suggestion): void
    {
        $ticket = $suggestion->ticket;
        $articles = $this->search->search("{$ticket->title}\n{$ticket->description}", self::MAX_ARTICLES);

        // Nothing in the knowledge base: no need to call the AI at all.
        if ($articles->isEmpty()) {
            $suggestion->update(['reply_status' => ReplyStatus::NoArticle]);

            return;
        }

        $maskedTicket = $this->formatter->format($ticket);

        try {
            $answer = $this->ai->ask(
                'reply',
                $ticket,
                $this->prompts->render('reply-system'),
                $this->prompts->render('reply-user', [
                    'ticket' => $maskedTicket->text,
                    'articles' => $this->renderArticles($articles),
                ]),
                $this->schema($articles),
            );
        } catch (AiProviderException) {
            $suggestion->update(['reply_status' => ReplyStatus::Failed]);

            return;
        }

        $draft = $this->parse($answer->response->text, $articles);

        if ($draft === null) {
            $this->ai->flagInvalidOutput($answer);
            $suggestion->update(['reply_status' => ReplyStatus::Failed]);

            return;
        }

        if (! $draft['has_answer']) {
            $suggestion->update(['reply_status' => ReplyStatus::NoArticle]);

            return;
        }

        DB::transaction(function () use ($suggestion, $draft, $maskedTicket): void {
            $suggestion->update([
                // Put the real names back: the technician reads the draft, not the tokens.
                'reply' => $this->masker->unmask($draft['reply'], $maskedTicket),
                'reply_status' => ReplyStatus::Proposed,
            ]);

            $suggestion->articles()->sync($draft['article_ids']);
        });
    }

    /**
     * @param  Collection<int, KbArticle>  $articles
     */
    private function renderArticles(Collection $articles): string
    {
        return $articles
            ->map(fn (KbArticle $article): string => sprintf(
                "<article id=\"%d\" titre=\"%s\">\n%s\n</article>",
                $article->id,
                e($article->title),
                Str::limit($article->content, self::MAX_ARTICLE_LENGTH),
            ))
            ->implode("\n\n");
    }

    /**
     * @param  Collection<int, KbArticle>  $articles
     * @return array<string, mixed>
     */
    private function schema(Collection $articles): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'has_answer' => ['type' => 'boolean'],
                'reply' => ['type' => 'string'],
                // Only the articles that were provided can be cited.
                'article_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => $articles->modelKeys()]],
            ],
            'required' => ['has_answer', 'reply', 'article_ids'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Validate the answer like any untrusted input. A draft that cites no article,
     * or an article that was never provided, is refused.
     *
     * @param  Collection<int, KbArticle>  $articles
     * @return array{has_answer: bool, reply: string, article_ids: list<int>}|null
     */
    private function parse(string $text, Collection $articles): ?array
    {
        $data = json_decode(trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($text))), true);

        if (! is_array($data)) {
            return null;
        }

        $validator = Validator::make($data, [
            'has_answer' => ['required', 'boolean'],
            'reply' => ['nullable', 'string', 'max:5000', 'required_if:has_answer,true'],
            'article_ids' => ['present', 'array', 'required_if:has_answer,true'],
            'article_ids.*' => ['integer', 'distinct', Rule::in($articles->modelKeys())],
        ]);

        if ($validator->fails()) {
            return null;
        }

        return [
            'has_answer' => (bool) $data['has_answer'],
            'reply' => trim((string) ($data['reply'] ?? '')),
            'article_ids' => array_map(intval(...), $data['article_ids']),
        ];
    }
}
