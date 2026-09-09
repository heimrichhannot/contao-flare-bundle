<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * Per-filter form builder handed to filter elements in buildForm().
 *
 * Besides the regular Symfony builder API for multi-field filters, it lets an element declare
 * itself as a single-field filter via {@see single()}. Single fields are mounted flat on the
 * root filter form under the filter's alias (query parameter `form[alias]=x`), and their
 * submitted value is handed back to buildFilter() as
 * {@see \HeimrichHannot\FlareBundle\Filter\FilterData::getSingleValue()}.
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
}
