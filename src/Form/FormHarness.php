<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use Symfony\Component\Form\FormInterface;

/**
 * The filters of one list within one form context: their root form and the mount <-> filter map.
 *
 * Created by {@see Factory\FormHarnessFactory}. Callers that only need the form go through
 * {@see getForm()}; callers that need to relate a mounted node back to its filter go through
 * {@see getMounts()}.
 *
 * @api
 */
final readonly class FormHarness
{
    /**
     * @param FormInterface $form Root filter form holding every mounted node.
     * @param array<string, FilterMount> $mounts Mounts keyed by alias.
     *
     * @internal Use {@see Factory\FormHarnessFactory} to create instances.
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
     * @return array<string, FilterMount> Mounts keyed by alias.
     */
    public function getMounts(): array
    {
        return $this->mounts;
    }

    public function getMount(string $alias): ?FilterMount
    {
        return $this->mounts[$alias] ?? null;
    }

    /**
     * The mounted form child of the given filter, or null when the root form has no such child.
     *
     * Null covers three cases, none of them an error: the filter never mounted (invalid alias, no
     * declared fields, cancelled build), a listener removed the child, or a listener replaced the
     * root builder wholesale ({@see \HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent::$formBuilder}).
     *
     * Resolution is deliberately lazy: form children may legally be added or removed by a
     * PRE_SUBMIT listener while the request is being handled, so the mount is looked up on every
     * call instead of being captured when the set was built.
     */
    public function getChild(string $alias): ?FormInterface
    {
        if (!isset($this->mounts[$alias])) {
            return null;
        }

        return $this->form->has($alias) ? $this->form->get($alias) : null;
    }

    /**
     * The value of every mounted filter, decoded by its control from the mount's data.
     *
     * On a submitted root form every mounted alias has an entry, null included (an emptied field
     * is a submission, not an absence). On an unsubmitted form only non-null entries are returned,
     * i.e. the hydrated initial values.
     *
     * @return array<string, ValueInterface|null> Decoded values keyed by alias.
     */
    public function decode(): array
    {
        $submitted = $this->form->isSubmitted();
        $values = [];

        foreach ($this->mounts as $mount)
        {
            $child = $this->getChild($mount->alias);

            if ($child === null || $mount->filter->control === null) {
                continue;
            }

            $value = $mount->filter->control->component->decode($child->getData(), $mount->scope);

            if ($submitted || $value !== null) {
                $values[$mount->alias] = $value;
            }
        }

        return $values;
    }
}
