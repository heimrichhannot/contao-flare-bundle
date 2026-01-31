<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Model;
use HeimrichHannot\FlareBundle\Contract\Config\ReaderPageMetaConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\ReaderPageMetaContract;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Event\FetchAutoItemEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ReaderManager
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FilterContextManager     $filterContextManager,
        private HtmlDecoder              $htmlDecoder,
        private ListItemProviderManager  $itemProvider,
        private ListQueryManager         $listQuery,
        private ListTypeRegistry         $listTypeRegistry,
        private FilterElementRegistry    $filterElementRegistry,
    ) {}

    /**
     * @throws FlareException
     */
    public function getModelByAutoItem(
        string|int|null $autoItem,
        ListModel       $listModel,
        ContentContext  $contentContext,
    ): ?Model {
        if (\is_null($autoItem)) {
            return null;
        }

        if (!($table = $listModel->dc)
            || !($filters = null/*$this->filterContextManager->collect($listModel, $contentContext)*/))
        {
            return null;
        }

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        $itemProvider = $this->itemProvider->ofList($listModel);
        $listQueryBuilder = $this->listQuery->prepare($listModel);

        ###> define auto_item filter context ###
        $autoItemDefinition = SimpleEquationElement::define(
            equationLeft: $listModel->getAutoItemField(),
            equationOperator: SqlEquationOperator::EQUALS,
            equationRight: $autoItem,
        )->setType('_flare_auto_item', $ogAlias);

        $autoItemFilterContext = $this->filterContextManager->definitionToContext(
            definition: $autoItemDefinition,
            listModel: $listModel,
            contentContext: $contentContext,
            descriptor: $this->filterElementRegistry->get($ogAlias),
        );
        ###< define auto_item filter context ###

        $event = $this->eventDispatcher->dispatch(
            new FetchAutoItemEvent(
                autoItem: $autoItem,
                autoItemFilterContext: $autoItemFilterContext,
                listModel: $listModel,
                contentContext: $contentContext,
                itemProvider: $itemProvider,
                listQueryBuilder: $listQueryBuilder,
                filters: $filters,
            )
        );

        if (!$autoItemFilterContext = $event->getAutoItemFilterContext()) {
            return null;
        }

        $itemProvider = $event->getItemProvider();
        $listQueryBuilder = $event->getListQueryBuilder();
        $filters = $event->getFilters();

        $filters->add($autoItemFilterContext);

        try
        {
            $ids = $itemProvider->fetchIds(
                listQueryBuilder: $listQueryBuilder,
                filters: $filters
            );
        }
        catch (\Exception $e)
        {
            throw new FlareException('Error fetching entries for auto_item.', previous: $e, source: __METHOD__);
        }

        if (!$ids) {
            return null;
        }

        if (\count($ids) > 1) {
            throw new FlareException('Multiple entries found for auto_item.', source: __METHOD__);
        }

        $id = (int) \reset($ids);

        if ($id < 1) {
            throw new FlareException('Invalid entry id.', source: __METHOD__);
        }

        return $modelClass::findByPk($id);
    }

    /**
     * @throws FlareException If no list type config is registered for the given list model type.
     */
    public function getPageMeta(ReaderPageMetaConfig $config): ReaderPageMetaDto
    {
        $listType = $config->getListSpecification()->type;

        if (!$listTypeConfig = $this->listTypeRegistry->get($listType))
        {
            throw new FlareException(
                \sprintf('No list type config registered for type "%s".', $listType),
                source: __METHOD__
            );
        }

        $service = $listTypeConfig->getService();

        if ($service instanceof ReaderPageMetaContract)
        {
            $pageMeta = $service->getReaderPageMeta($config);
        }

        $pageMeta ??= new ReaderPageMetaDto();

        if (!$pageMeta->getTitle())
        {
            $model = $config->getContentModel();
            $pageMeta->setTitle($this->htmlDecoder->inputEncodedToPlainText(
                $model->title ?? $model->headline ?? $model->name ?? $model->alias ?? $model->id ?: ''
            ));
        }

        return $pageMeta;
    }
}