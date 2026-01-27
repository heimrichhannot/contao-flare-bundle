<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Dto\FetchSingleEntryConfig;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Projector\Projection\ValidationProjection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProjectorInterface<ValidationProjection>
 */
class ValidationProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return ListContext::VALIDATION;
    }

    public static function getProjectionClass(): string
    {
        return ValidationProjection::class;
    }

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProviderManager  $itemProviderManager,
        private readonly ListQueryManager         $listQueryManager
    ) {}

    protected function execute(ListContext $listContext, ListDefinition $listDefinition): ValidationProjection
    {
        return new ValidationProjection(
            fetchEntry: function (int $id) use ($listContext, $listDefinition): ?array {
                return $this->fetchEntry($id, $listContext, $listDefinition);
            },
            table: $listDefinition->dc,
            entries: $listContext->get('validation.entries'),
        );
    }

    public function fetchEntry(int $id, ListContext $listContext, ListDefinition $listDefinition): ?array
    {
        $itemProvider = $this->itemProviderManager->ofList($listDefinition);
        $listQueryBuilder = $this->listQueryManager->prepare($listDefinition);

        $idDefinition = SimpleEquationElement::define(
            equationLeft: 'id',
            equationOperator: SqlEquationOperator::EQUALS,
            equationRight: $id,
        )->setType('_flare_id', $ogAlias);

        /**
         * @noinspection PhpParenthesesCanBeOmittedForNewCallInspection
         * @noinspection RedundantSuppression
         * @var FetchListEntriesEvent $event
         */
        $event = $this->eventDispatcher->dispatch(
            (new FetchListEntriesEvent(
                listContext: $listContext,
                listDefinition: $listDefinition,
                itemProvider: $itemProvider,
                listQueryBuilder: $listQueryBuilder,
            ))->withSingleEntryConfig(new FetchSingleEntryConfig($id, $idDefinition))
        );

        $idDefinition = $event->getSingleEntryConfig()->idFilterDefinition ?? $idDefinition;
        $listDefinition->filters->add($idDefinition);

        $itemProvider = $event->getItemProvider();
        $listQueryBuilder = $event->getListQueryBuilder();

        $entries = $itemProvider->fetchEntries(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $listDefinition,
            listContext: $listContext,
        );

        return \reset($entries) ?: null;
    }
}