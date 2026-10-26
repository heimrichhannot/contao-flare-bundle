<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;

/**
 * Implemented by filter elements whose value is picked from a server-provided option set.
 *
 * The capability port a generic choice control and the `ChoiceValueProvider` depend on
 * (SPEC_FILTER_FORMS.md §3.5). It is the one source of options for the frontend widget and the
 * backend value field (§6.4).
 *
 * @api
 */
interface ChoiceSourceContract
{
    /**
     * Adds the element's choices for the given filter scope to a fresh builder the caller provides.
     *
     * Labelling is split: this method MAY set labels the element owns (setLabelForTable() fed from
     * element-owned whitelist rows); the control owns only global overrides.
     *
     * @throws FilterException On invalid filter configuration (no whitelist, no inferrable ptable,
     *   no valid target field, …).
     */
    public function buildChoices(ChoicesBuilder $choices, FilterScope $scope): void;

    /**
     * A reference choice for a canonical key — no database access; null for an unreadable key.
     *
     * Only the element knows which choice class a key belongs to ("tl_news_archive.5" is table + id
     * for Archive, but may be a plain field value containing a dot for FieldValueChoice).
     */
    public function choiceFromKey(string $key): ?ChoiceObjectInterface;
}
