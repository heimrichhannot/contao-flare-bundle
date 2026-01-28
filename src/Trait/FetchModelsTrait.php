<?php

namespace HeimrichHannot\FlareBundle\Trait;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;

trait FetchModelsTrait
{
    public function fetchModel(string $table, int $id, callable $getEntry): Model
    {
        $registry = Model\Registry::getInstance();
        if ($model = $registry->fetch($table, $id))
            // Contao native model cache
        {
            return $model;
        }

        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        if (!$row = $getEntry($id)) {
            throw new FlareException('Invalid entry id.', source: __METHOD__);
        }

        $model = new $modelClass($row);
        if (!$model instanceof Model) {
            throw new FlareException('Invalid model instance.', source: __METHOD__);
        }

        $registry->register($model);

        return $model;
    }

    public function registerModelsFromEntries(string $table, array $entries): array
    {
        $modelClass = Model::getClassFromTable($table);
        if (!\class_exists($modelClass)) {
            throw new FlareException(\sprintf('Model class does not exist: "%s"', $modelClass), source: __METHOD__);
        }

        $registry = Model\Registry::getInstance();
        $models = [];

        foreach ($entries as $entry)
        {
            if (!$id = $entry['id'] ?? null) {
                throw new FlareException('Entry does not have an ID.', source: __METHOD__);
            }

            if (!$model = $registry->fetch($table, $id))
                // Contao native model cache
            {
                $model = new $modelClass($entry);

                if (!$model instanceof Model) {
                    throw new FlareException('Invalid model instance.', source: __METHOD__);
                }

                $registry->register($model);
            }

            $models[$id] = $model;
        }

        return $models;
    }
}