<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;

class ValidationView implements ViewInterface
{
    use HandlesModelsTrait;
    use LinksToReaderTrait;

    private array $entriesById;
    private array $entriesByAutoItem;

    public function __construct(
        private readonly ValidationLoaderInterface   $loader,
        private readonly ReaderUrlGeneratorInterface $readerUrlGenerator,
        private readonly string                      $table,
        private readonly string                      $autoItemField,
    ) {}

    public function getEntry(int $id): ?array
    {
        return $this->entriesById[$id] ??= $this->loader->fetchEntryById($id);
    }

    public function getEntryByAutoItem(string $autoItem): ?array
    {
        return $this->entriesByAutoItem[$autoItem] ??= $this->loader->fetchEntryByAutoItem($autoItem);
    }

    public function getModel(int $id): ?Model
    {
        return $this->fetchModel($this->table, $id, $this->getEntry(...));
    }

    public function getModelByAutoItem(string $autoItem): ?Model
    {
        return $this->fetchModel($this->table, $autoItem, $this->getEntryByAutoItem(...), $this->autoItemField);
    }

    protected function getReaderModel(int $id): ?Model
    {
        return $this->getModel($id);
    }

    protected function getReaderUrlGenerator(): ReaderUrlGeneratorInterface
    {
        return $this->readerUrlGenerator;
    }
}