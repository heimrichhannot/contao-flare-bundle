<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Context\ValidationConfig;
use HeimrichHannot\FlareBundle\Dto\FetchSingleEntryConfig;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\View\ValidationView;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProjectorInterface<ValidationView>
 */
class ValidationProjector extends AbstractProjector
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProviderManager  $itemProviderManager,
        private readonly ListQueryManager         $listQueryManager
    ) {}

    public function supports(ContextConfigInterface $config): bool
    {
        return $config instanceof ValidationConfig;
    }

    public function project(ListSpecification $spec, ContextConfigInterface $config): ValidationView
    {
        \assert($config instanceof ValidationConfig);

        return new ValidationView(
            fetchEntry: function (int $id) use ($spec, $config): ?array {
                return $this->fetchEntry($id, $spec, $config);
            },
            table: $spec->dc,
        );
    }

    public function fetchEntry(int $id, ListSpecification $spec, ValidationConfig $config): ?array
    {
        // Fast lane cache check
        if ($hit = $config->getEntryCache()[$id] ?? null) {
            return $hit;
        }

        // IMPORTANT: clone the spec to not modify the original, i.e., when adding the id filter
        $spec = clone $spec;

        $idDefinition = SimpleEquationElement::define(
            equationLeft: 'id',
            equationOperator: SqlEquationOperator::EQUALS,
            equationRight: $id,
        );

        $spec->filters->add($idDefinition);

        $listQueryBuilder = $this->listQueryManager->prepare($spec);
        $itemProvider = $this->itemProviderManager->ofList($spec);

        /**
         * @noinspection PhpParenthesesCanBeOmittedForNewCallInspection
         * @noinspection RedundantSuppression
         * @var FetchListEntriesEvent $event
         */
        $event = $this->eventDispatcher->dispatch(
            (new FetchListEntriesEvent(
                contextConfig: $config,
                listSpecification: $spec,
                itemProvider: $itemProvider,
                listQueryBuilder: $listQueryBuilder,
            ))->withSingleEntryConfig(new FetchSingleEntryConfig($id, $idDefinition))
        );

        $itemProvider = $event->getItemProvider();
        $listQueryBuilder = $event->getListQueryBuilder();

        $entries = $itemProvider->fetchEntries(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $spec,
            contextConfig: $config,
        );

        return \reset($entries) ?: null;
    }
}