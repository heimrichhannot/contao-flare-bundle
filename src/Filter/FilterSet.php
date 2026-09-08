<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use Symfony\Component\Form\FormInterface;

/**
 * The filters of one list within one form context: their root form and the mount↔filter map.
 *
 * Created by {@see Factory\FilterSetFactory}. Callers that only need the form go through
 * {@see getForm()}; callers that need to relate a mounted node back to its filter go through
 * {@see getMounts()}.
 *
 * @api
 */
final readonly class FilterSet
{
    /**
     * @param FormInterface $form Root filter form holding every mounted node.
     * @param array<string|int, FilterMount> $mounts Mounts keyed by the filter's key within
     *   {@see \HeimrichHannot\FlareBundle\List\ListSpec::$filters}.
     *
     * @internal Use {@see Factory\FilterSetFactory} to create instances.
     */
    public function __construct(
        private FormInterface $form,
        private array         $mounts = [],
    ) {}

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    /**
     * @return array<string|int, FilterMount> Mounts keyed by the filter's list-specification key.
     */
    public function getMounts(): array
    {
        return $this->mounts;
    }

    public function getFilterMount(string|int $key): ?FilterMount
    {
        return $this->mounts[$key] ?? null;
    }

    /**
     * The mounted form child of the given filter, or null when the root form has no such child.
     *
     * Null covers three cases, none of them an error: the filter never mounted (invalid alias, no
     * declared fields, cancelled build), a listener removed the child, or a listener replaced the
     * root builder wholesale ({@see \HeimrichHannot\FlareBundle\Event\FilterSetBuildEvent::$formBuilder}).
     *
     * Resolution is deliberately lazy: form children may legally be added or removed by a
     * PRE_SUBMIT listener while the request is being handled, so the mount is looked up on every
     * call instead of being captured when the set was built.
     */
    public function getMount(string|int $key): ?FormInterface
    {
        // Compared against null, not truthiness: Str::isValidFormName() permits "0" as an alias.
        $alias = ($this->mounts[$key] ?? null)?->alias;

        if ($alias === null) {
            return null;
        }

        return $this->form->has($alias) ? $this->form->get($alias) : null;
    }
}
