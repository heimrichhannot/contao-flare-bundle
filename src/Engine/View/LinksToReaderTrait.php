<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Exception\FlareException;

trait LinksToReaderTrait
{
    private array $readerUrls = [];

    /**
     * @return Model|null     The resolved model or `null` if a model of the given ID is not available.
     * @throws FlareException If an issue not pertaining to the availability of the requested model occurs.
     *   E.g., when the model class cannot be resolved. Possibly bubbling from {@see HandlesModelsTrait}.
     */
    abstract protected function getReaderModel(int $id): ?Model;

    /**
     * @return callable(Model $model): ?string
     */
    abstract protected function getReaderUrlGenerator(): callable;

    /**
     * Generates and retrieves the reader URL for a given model or ID.
     *
     * @param Model|int|string $target The model instance or its ID.
     * @return string|null The generated URL, or null if the model cannot be resolved.
     * @throws FlareException Thrown by {@see getReaderModel()}.
     * @throws \InvalidArgumentException If the provided model does not match the expected model type for this context.
     */
    public function to(Model|int|string $target): ?string
    {
        $id = (int) ($target instanceof Model ? $target->id : $target);

        if (\array_key_exists($id, $this->readerUrls)) {
            return $this->readerUrls[$id];
        }

        if (!$model = $this->getReaderModel($id)) {
            return $this->readerUrls[$id] = null;
        }

        if ($target instanceof Model && $model->id !== $target->id && $model::getTable() !== $target::getTable()) {
            throw new \InvalidArgumentException('The provided model does not match the model resolved by the list context.');
        }

        return $this->readerUrls[$id] = ($this->getReaderUrlGenerator())($model);
    }
}