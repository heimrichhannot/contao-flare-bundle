<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;

/**
 * A submitted boolean: the value consumed by `BooleanFilterElement`.
 *
 * Two states, not three. "No opinion" — no submission, an empty submission, or a falsy submission
 * under {@see BoolBinaryChoices::NULL_TRUE} — is the *absence* of this value (a plain `null`),
 * never an instance carrying `null`. SPEC_FILTER_FORMS.md §9 requires a single representation per
 * state, and a constructor cannot normalise itself away.
 *
 * {@see tryFrom()} is the lifted, pure form of `BooleanFilterElement::normalizeValue()`. It has two
 * callers in the target model — the form's `decode()` and the form's `preselect` transformer
 * (§4.2) — which is why it lives here rather than on either of them.
 */
final readonly class BoolValue implements ValueInterface
{
    public function __construct(
        public bool $state,
    ) {}

    /**
     * @param mixed $value Raw submitted or configured value.
     * @param BoolBinaryChoices|null $choices Binary-choice variant, or null for no collapse (the
     *   `preselect` transformer's case, which must not fold a falsy value to "no opinion").
     */
    public static function tryFrom(mixed $value, ?BoolBinaryChoices $choices = null): ?self
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
        }

        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        if ($choices === BoolBinaryChoices::NULL_TRUE && !$value) {
            return null;
        }

        $state = \filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

        return $state === null ? null : new self($state);
    }
}
