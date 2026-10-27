<?php

namespace App\Assistant;

use App\Enum\Track;

/**
 * What the review assistant writes about a proposal, as structured output of the model.
 */
final class ReviewNotes
{
    /** A summary of the proposal for the reviewers, in two or three sentences, that names the similar talks and proposals */
    public string $summary;

    /** @var list<string> Up to five tags, in lowercase */
    public array $tags = [];

    /** The track that fits the proposal best */
    public ?Track $track = null;
}
