<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\DataContainer\Builder;

interface DcaFieldBuilderInterface
{
    public function inputType(string $inputType): self;

    public function eval(array $eval): self;

    public function merge(array $definition): self;

    /**
     * Removes keys of the field definition, given as dot paths (`'eval.rgxp'`, `'wizard'`), before the builder's own
     * definition is merged in; a later `eval()` or `merge()` sets a key again.
     *
     * Scope: the keys of the definition and of `eval()`/`merge()` only. The label, the options and the callbacks have
     * their own setters, which are applied after the removal; `remove()` does not touch what they set.
     */
    public function remove(string ...$paths): self;

    /**
     * Makes the field a date-time input (`rgxp` datim, date picker, `wizard` class) that shows a stored expression
     * as its timestamp.
     */
    public function makeDatim(): self;

    /**
     * Replaces the field's label and description, e.g. for a field whose meaning depends on the type.
     */
    public function label(string $label, ?string $description = null): self;

    public function options(callable|array $options): self;

    public function load(callable $fn): self;

    public function save(callable $fn): self;
}
