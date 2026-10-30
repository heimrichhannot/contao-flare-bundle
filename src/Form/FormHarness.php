<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Form;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
     * @param TranslatorInterface|null $translator Translates the form error of a rejected submission.
     * @param LoggerInterface|null $logger Receives a warning for each value a control rejects, an error for each
     *   unexpected failure.
     * @param bool $debug Whether an unexpected failure is rethrown instead.
     * @param list<FilterFailure> $failures The filters whose control could not build its form; they fail the list.
     *
     * @internal Use {@see Factory\FormHarnessFactory} to create instances.
     */
    public function __construct(
        private FormInterface        $form,
        private array                $mounts = [],
        private ?TranslatorInterface $translator = null,
        private ?LoggerInterface     $logger = null,
        private bool                 $debug = false,
        private array                $failures = [],
    ) {}

    /**
     * The filters whose control could not build its form. Each fails the list as a whole; the form holds the other
     * filters' mounts.
     *
     * @return list<FilterFailure>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

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
     * Null covers three cases: the filter never mounted (its control failed, see {@see getFailures()}; no
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
     * A control that rejects its data ({@see FilterException} from decode()) contributes no value. A
     * rejected submission also gets a form error on its mount, which makes the form invalid like any
     * other invalid input: the list renders empty and the field shows the error. A rejected initial
     * value is dropped with a warning only, since the visitor did not enter it. Any other exception
     * is treated the same, since the list must not widen silently, but logged as an error; in debug
     * mode it is rethrown wrapped instead.
     *
     * @return array<string, ValueInterface|null> Decoded values keyed by alias.
     *
     * @throws FilterException In debug mode, if a control fails unexpectedly.
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

            try
            {
                $value = $mount->filter->control->component->decode($child->getData(), $mount->scope);
            }
            catch (\Exception $e)
            {
                $method = $mount->filter->control->component::class . '::decode';
                $this->reject($mount, $child, $submitted, $e, $method);
                $value = null;
            }

            if ($submitted || $value !== null) {
                $values[$mount->alias] = $value;
            }
        }

        return $values;
    }

    /**
     * @throws FilterException In debug mode, for an unexpected exception.
     */
    private function reject(
        FilterMount   $mount,
        FormInterface $child,
        bool          $submitted,
        \Exception    $e,
        string        $method,
    ): void {
        $expected = $e instanceof FilterException;
        $e = FilterException::wrap($e, $mount->filter, $method);

        if (!$expected && $this->debug) {
            throw $e;
        }

        $this->logger?->log(
            $expected ? 'warning' : 'error',
            \sprintf(
                '[FLARE] The control of filter "%s" could not decode its data, which is dropped: %s',
                $mount->alias,
                $e->getMessage(),
            ),
            ['alias' => $mount->alias, 'exception' => $e],
        );

        if (!$submitted) {
            return;
        }

        $message = 'flare.form.invalid_value';

        $child->addError(new FormError(
            $this->translator?->trans($message, [], 'validators') ?? $message,
            $message,
            cause: $e,
        ));
    }
}
