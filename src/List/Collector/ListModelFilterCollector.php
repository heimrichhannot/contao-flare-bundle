<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Collector;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\FilterCollectedEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Collects the published tl_flare_filter rows of a list model as Filter DTOs,
 * translating each row through its element's transformers.
 */
readonly class ListModelFilterCollector
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private FilterFactory             $filterFactory,
        private ListDriverRegistry        $listDriverRegistry,
        private LoggerInterface           $logger,
    ) {}

    /**
     * @return array<string, Filter>|null
     */
    public function collect(ListModel $listModel): ?array
    {
        if (!$listModel->id || !$table = $listModel::getTable()) {
            return null;
        }

        if (!$this->listDriverRegistry->getService((string) $listModel->type)) {
            return null;
        }

        Controller::loadDataContainer($table);

        $filters = [];

        /** @var FilterModel $model */
        foreach (FilterModel::findByPid((int) $listModel->id, published: true) as $model)
            // Collect filters defined in the backend
        {
            if (!$model->published) {
                continue;
            }

            try
            {
                $filter = $this->filterFactory->createFromFilterModel($model);
            }
            catch (FlareException $e)
            {
                $this->logger->warning(\sprintf(
                    '[FLARE] Error while creating Filter of type "%s" on [%s.%s] -- [Message] %e',
                    $model->getFilterElementType(),
                    $listModel::getTable(),
                    $listModel->id,
                    $e->getMessage(),
                ));

                continue;
            }

            $filter = $this->eventDispatcher->dispatch(new FilterCollectedEvent($filter, $model))->filter;

            $filters[$filter->alias] = $filter;
        }

        return $filters;
    }
}
