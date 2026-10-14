<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use Symfony\Component\Form\FormBuilder;

/**
 * Collect-only builder for a single filter's form fields.
 *
 * Constructed manually by {@see Factory\FilterFormFactory} outside Symfony's form-type system,
 * so it carries no resolved type, options, or data mapper and must never be mounted into a form
 * tree — the factory transfers its children, attributes, single-field spec, and deferred event
 * listeners onto a real builder. Children created through add()/create() are real, factory-built
 * builders because they route through the injected form factory.
 */
class FilterFormBuilder extends FormBuilder implements FilterFormBuilderInterface
{
    /** @var array{type: class-string, options: array<string, mixed>}|null */
    private ?array $single = null;

    /** @var list<array{string, callable, int}> */
    private array $deferredListeners = [];

    public function single(string $type, array $options = []): static
    {
        $this->single = ['type' => $type, 'options' => $options];

        return $this;
    }

    public function getSingle(): ?array
    {
        return $this->single;
    }

    /**
     * Records the listener for the factory to replay on the mounted builder — this collector's
     * own dispatcher never dispatches. Parameters are deliberately untyped: the bundle supports
     * Symfony ^5.4|^6|^7, whose signatures differ in native parameter types.
     *
     * @param string $eventName
     * @param callable $listener
     * @param int $priority
     */
    public function addEventListener($eventName, $listener, $priority = 0): static
    {
        $this->deferredListeners[] = [$eventName, $listener, $priority];

        return $this;
    }

    /**
     * @return list<array{string, callable, int}>
     */
    public function getDeferredListeners(): array
    {
        return $this->deferredListeners;
    }

    /**
     * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $subscriber
     */
    public function addEventSubscriber($subscriber): never
    {
        throw new \LogicException(
            'Event subscribers are not supported on the per-filter form builder.'
            . ' Use addEventListener() (replayed onto the mounted form) or register listeners'
            . ' on the field builders instead.',
        );
    }

    public function getForm(): never
    {
        throw new \LogicException(\sprintf(
            '%s is a collect-only builder and cannot produce a form; it is never mounted.'
            . ' FilterFormFactory transfers its fields onto a real builder.',
            self::class,
        ));
    }
}
