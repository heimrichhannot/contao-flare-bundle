<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListQuery;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;

class ListQueryManager
{
    public const ALIAS_MAIN = 'main';

    public function __construct(
        private readonly Connection           $connection,
        private readonly ListTypeRegistry     $listTypeRegistry,
        private readonly FlareCallbackManager $callbackManager,
    ) {}

    /**
     * @throws FlareException
     */
    public function prepare(ListModel $listModel): ListQuery
    {
        /** @var ListTypeDescriptor $type */
        if (!$type = $this->listTypeRegistry->get($listModel->type)) {
            throw new FlareException(\sprintf('No list type registered for type "%s".', $listModel->type), method: __METHOD__);
        }

        if (!$mainTable = $listModel->dc ?? $type->getDataContainer()) {
            throw new FlareException('No data container table set.', method: __METHOD__);
        }

        $builder = new ListQueryBuilder(
            connection: $this->connection,
            mainFrom: $mainTable,
            mainAlias: self::ALIAS_MAIN,
        );

        $callbacks = $this->callbackManager->getListCallbacks($listModel->type, 'query.configure');

        CallbackHelper::call($callbacks, [], [
            ListModel::class => $listModel,
            ListQueryBuilder::class => $builder,
        ]);

        return $builder->buildQuery();
    }
}