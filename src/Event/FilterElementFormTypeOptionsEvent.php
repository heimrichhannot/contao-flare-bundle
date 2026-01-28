<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class FilterElementFormTypeOptionsEvent extends Event implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    private ?ChoicesBuilder $choicesBuilder;

    public function __construct(
        public readonly ListSpecification $listDefinition,
        public readonly FilterDefinition  $filterDefinition,
        public array                      $options,
    ) {}

    public function getChoicesBuilder(): ChoicesBuilder
    {
        if (!isset($this->choicesBuilder)) {
            /** @var ChoicesBuilderFactory $factory */
            if (!$factory = $this->container->get(ChoicesBuilderFactory::class)) {
                throw new \RuntimeException('No ChoicesBuilderFactory found.');
            }
            $this->choicesBuilder = $factory->createChoicesBuilder();
        }

        return $this->choicesBuilder;
    }

    public function isChoicesBuilderEnabled(): bool
    {
        return isset($this->choicesBuilder) && $this->choicesBuilder->isEnabled();
    }

    public function resetChoicesBuilder(): self
    {
        $this->choicesBuilder = null;
        return $this;
    }

    public static function getSubscribedServices(): array
    {
        return [ChoicesBuilderFactory::class];
    }
}