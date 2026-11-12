<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Controller;
use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;

trait HandlesModelsTrait
{
    /**
     * @param callable(int|string $id_or_alias): ?array $getEntry
     * @return Model|null Return null if the entry does not exist.
     * @throws FlareException
     */
    public function fetchModel(string $table, int|string $id_or_alias, callable $getEntry, ?string $column = null): ?Model
    {
        $registry = Model\Registry::getInstance();
        if ($model = $registry->fetch($table, $id_or_alias, strAlias: $column))
            // Contao native model cache
        {
            Controller::loadDataContainer($table);

            if (!isset($GLOBALS['TL_DCA'][$table]['fields']['published']) || $model->published) {
                return $model;
            }
        }

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), method: __METHOD__);
        }

        if (!$row = $getEntry($id_or_alias)) {
            return null;
        }

        $model = new $modelClass($row);
        if (!$model instanceof Model) {
            throw new FlareException('Invalid model instance.', method: __METHOD__);
        }

        $registry->register($model);

        return $model;
    }
}
