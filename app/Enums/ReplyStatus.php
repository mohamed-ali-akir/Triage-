<?php

namespace App\Enums;

/**
 * What happened when the AI was asked to draft a reply from the knowledge base.
 */
enum ReplyStatus: string
{
    // A draft exists, with the articles it relies on.
    case Proposed = 'proposed';

    // No article answers this ticket: the AI says so instead of inventing an answer.
    case NoArticle = 'no_article';

    // The AI could not be reached or gave an unusable answer.
    case Failed = 'failed';
}
