<?php

namespace HeimrichHannot\FlareBundle\Projector;

use Contao\PageModel;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\PaginatorBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Manager\FilterFormManager;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Projector\Projection\AggregationProjection;
use HeimrichHannot\FlareBundle\Projector\Projection\InteractiveProjection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InteractiveProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return 'interactive';
    }

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterFormManager        $filterFormManager,
        private readonly RequestStack             $requestStack,
        private readonly ListItemProviderManager  $itemProvider,
        private readonly ListQueryManager         $listQueryManager,
        private readonly PaginatorBuilderFactory  $paginatorBuilderFactory,
        private readonly Projectors               $projectors,
    ) {}

    protected function execute(ListContext $context, ListDefinition $listDefinition): InteractiveProjection
    {
        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new FilterException('Request not available', source: __METHOD__);
        }

        $aggregate = $this->getAggregationProjection($context, $listDefinition);

        $proj = new InteractiveProjection(
            aggregationProjection: $aggregate,
            eventDispatcher: $this->eventDispatcher,
            formManager: $this->filterFormManager,
            listContext: $context,
            listDefinition: $listDefinition,
            itemProvider: $this->itemProvider,
            listQueryManager: $this->listQueryManager,
            paginatorBuilderFactory: $this->paginatorBuilderFactory,
            request: $request,
        );

        return $proj;
    }

    private function getAggregationProjection(ListContext $context, ListDefinition $definition): AggregationProjection
    {
        /** @var AggregationProjector $projector */
        if (!$projector = $this->projectors->get(AggregationProjector::getContext())) {
            throw new FlareException('Aggregation projector not available', source: __METHOD__);
        }

        $projection = $projector->project($context, $definition);

        if (!$projection instanceof AggregationProjection) {
            throw new FlareException('Aggregation projection not available', source: __METHOD__);
        }

        return $projection;
    }
}