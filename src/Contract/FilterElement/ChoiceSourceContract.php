<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;

/**
 * Implemented by filter elements whose value is picked from a server-provided option set.
 *
 * The capability port a generic choice form declares in `AsFilterForm::$requires`
 * (SPEC_FILTER_FORMS.md §3.4). The division it enforces:
 *
 * > The **form** decodes the *widget*: which choice keys did the user pick.
 * > The **element** decodes the *domain*: what do those keys mean.
 *
 * Two methods, not one, because choice keys are element-defined: `FieldValueChoice` uses bare field
 * values, `CodefogTagsChoice` tag ids, `ArchiveFilterElement` `"<table>.<id>"` in dynamic-ptable
 * mode (§7.4). A form that parsed them would be reading element knowledge through the back door —
 * the very thing `requires` exists to prevent.
 *
 * Labelling is split: buildChoices() MAY set labels the element owns (setLabelForTable() fed from
 * element-owned whitelist rows is the real case); the form owns only global overrides (setLabel(),
 * setModelSuffix(), setEmptyOption()) and applyFormOptions().
 *
 * @api
 */
interface ChoiceSourceContract
{
    /**
     * Builds the element's choices for the current filter invocation.
     *
     * Doubles as the single source of truth for the choice set: the same builder serves the form's
     * field, the choice value callback and — via §7.2's view-data decode — the reverse mapping,
     * which therefore no longer needs a second, rebuilt ChoicesBuilder.
     *
     * @throws FilterException On invalid filter configuration (no whitelist, no inferrable ptable,
     *   no valid target field, …).
     */
    public function buildChoices(FilterContext $context): ChoicesBuilder;

    /**
     * Interprets submitted choice keys as this element's domain value.
     *
     * @param list<string> $keys Selected choice keys, verbatim — MAY include
     *   {@see ChoicesBuilder::EMPTY_CHOICE}, whose meaning is element-defined. The sentinel is
     *   passed through rather than swallowed because it can carry domain meaning:
     *   ArchiveFilterElement treats a selected empty option as "use the full whitelist" unless
     *   `use_whitelist_for_options_only`.
     *
     * @return object|null A value object of the class this element declares in
     *   `AsFilterElement::$value`; null when the keys carry no query information.
     */
    public function valueFromChoiceKeys(array $keys, FilterContext $context): ?object;
}
