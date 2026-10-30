<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * Per-filter form builder handed to filter controls in buildForm().
 *
 * Besides the regular Symfony builder API for multi-field filters, it lets a control declare
 * itself as a single-field filter via {@see single()}. Single fields are mounted flat on the
 * root filter form under the filter's alias (query parameter `form[alias]=x`). Either way, the
 * mount's data is handed back to the control's
 * {@see \HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface::decode()}.
 */
interface FilterFormBuilderInterface extends FormBuilderInterface
{
    /**
     * Declares this filter as a single-field filter of the given form type.
     *
     * The field is not added as a child; the form factory mounts it under the filter's alias.
     * Calling this method again overwrites the previous declaration. Declaring a single field
     * and adding children at the same time is not supported and fails when the form is built.
     *
     * @param class-string $type Form type class of the field.
     * @param array<string, mixed> $options Form options of the field.
     */
    public function single(string $type, array $options = []): static;

    /**
     * @return array{type: class-string, options: array<string, mixed>}|null
     */
    public function getSingle(): ?array;

    /**
     * Sets form options of the compound mount of a multi-field filter, e.g. its `label`; merged over the
     * mount's defaults (no label, not required). Ignored for a single-field filter, whose options are
     * passed to {@see single()}. Calling this method again overwrites the previous options.
     *
     * @param array<string, mixed> $options Form options of the compound mount.
     */
    public function compound(array $options): static;

    /**
     * @return array<string, mixed>
     */
    public function getCompoundOptions(): array;
}
