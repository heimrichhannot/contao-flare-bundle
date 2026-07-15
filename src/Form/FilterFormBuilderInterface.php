<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * Per-filter form builder handed to filter elements in buildForm().
 *
 * Besides the regular Symfony builder API for multi-field filters, it lets an element declare
 * itself as a single-field filter via {@see single()}. Single fields are mounted flat on the
 * root filter form under the filter's alias (query parameter `form[alias]=x`), while their
 * submitted value is always handed back to buildFilter() under
 * {@see \HeimrichHannot\FlareBundle\Filter\FilterContext::SINGLE_VALUE}.
 */
interface FilterFormBuilderInterface extends FormBuilderInterface
{
    /**
     * Declares this filter as a single-field filter of the given form type.
     *
     * The field is not added as a child; the form factory mounts it under the filter's alias.
     * Calling this method again overwrites the previous declaration. If children are added
     * alongside, the single field is materialized under
     * {@see \HeimrichHannot\FlareBundle\Filter\FilterContext::SINGLE_VALUE} within the
     * compound filter form instead.
     *
     * @param class-string $type Form type class of the field.
     * @param array<string, mixed> $options Form options of the field.
     */
    public function single(string $type, array $options = []): static;

    /**
     * @return array{type: class-string, options: array<string, mixed>}|null
     */
    public function getSingle(): ?array;
}
