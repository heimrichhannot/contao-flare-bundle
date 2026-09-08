<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

/**
 * A free-text search phrase: the value consumed by `SearchKeywordsFilterElement`.
 *
 * The searched *columns* are deliberately absent. They change which rows match, so they are element
 * config (SPEC_FILTER_FORMS.md §4.1) — a form must not be able to redirect the search — and
 * `Filter::fingerprint()` already carries `$config`, so restating them here would duplicate them
 * into the hash under an arbitrary order.
 *
 * Normalised to a single-spaced, trimmed phrase: `SearchKeywordsFilterType` splits on
 * `/\s+OR\s+/i` and then lowercases and re-tokenises each group, so whitespace variants are
 * multiple representations of one query (§9). Case is *not* folded — the filter type does that
 * itself, and folding here would discard the user's input verbatim for no query benefit.
 *
 * Distinct from {@see ChoiceValue} by semantics, not structure (§4.3).
 */
final readonly class KeywordsValue
{
    /** Non-empty, trimmed, with internal whitespace runs collapsed to a single space. */
    public string $keywords;

    public function __construct(string $keywords)
    {
        $this->keywords = \trim((string) \preg_replace('/\s+/', ' ', $keywords));
    }

    public static function tryFrom(mixed $keywords): ?self
    {
        if ($keywords instanceof \Stringable) {
            $keywords = (string) $keywords;
        }

        if (!\is_string($keywords)) {
            return null;
        }

        $value = new self($keywords);

        return $value->keywords === '' ? null : $value;
    }
}
