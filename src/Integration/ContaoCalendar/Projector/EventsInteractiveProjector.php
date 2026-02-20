<?php

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Projector\AbstractProjector;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class EventsInteractiveProjector extends AbstractProjector
{
    public function __construct(
        private readonly ProjectorRegistry $projectorRegistry,
    ) {}

    public function supports(ListSpecification $spec, ContextInterface $config): bool
    {
        return $spec->type === EventsListType::TYPE && $config instanceof InteractiveContext;
    }

    public function priority(ListSpecification $spec, ContextInterface $config): int
    {
        return 100;
    }

    public function project(ListSpecification $spec, ContextInterface $config): ViewInterface
    {
        \assert($config instanceof InteractiveContext, '$config must be an instance of InteractiveConfig');

        $regularProjector = $this->projectorRegistry->getProjectorFor($spec, $config, exclude: [ self::class ]);
        $view = $regularProjector->project($spec, $config);
        \assert($view instanceof InteractiveView, 'Expected InteractiveView');

        return $view;
    }
}