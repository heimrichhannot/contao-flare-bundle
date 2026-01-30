<?php

namespace HeimrichHannot\FlareBundle\Engine\View\Trait;

use Contao\Model;

trait LinksToReaderTrait
{
    private array $readerUrls = [];

    abstract protected function getReaderModel(int $id): ?Model;

    /**
     * @return callable(Model $model): ?string
     */
    abstract protected function getReaderUrlGenerator(): callable;

    /**
     * Generates and retrieves the reader URL for a given ID.
     *
     * @param Model|int|string $target The target model or ID.
     * @return string|null The URL as a string if available, or null if not found.
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