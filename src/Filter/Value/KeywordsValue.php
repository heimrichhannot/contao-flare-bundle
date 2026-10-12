<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

/**
 * A free-text search phrase, normalized to a single-spaced, trimmed phrase.
 */
final readonly class KeywordsValue implements ValueInterface
{
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
